<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AutorisationSortie;
use App\Models\Etablissement;
use App\Models\Etudiant;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\IdentiteEtablissement;
use App\Services\NotificationParentsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Autorisations de sortie anticipée.
 * - Le parent remplit et signe (nom tapé + attestation) ; l'horodatage, l'IP et une empreinte SHA-256 sont conservés.
 * - La vie scolaire valide ou refuse, puis note l'heure réelle de sortie ; les parents en sont prévenus.
 */
class AutorisationSortieController extends Controller
{
    private const ROLES_ECOLE = 'admin,proviseur,censeur,cpe,surveillant,secretaire';

    public function __construct()
    {
        $this->middleware('role:parent')->only(['mesSorties', 'signer', 'annuler', 'preferences', 'enregistrerPreferences']);
        $this->middleware('role:'.self::ROLES_ECOLE)->only(['index', 'decider', 'marquerSortie']);
    }

    // ---------------------------------------------------------------- Parent

    public function mesSorties(Request $request): JsonResponse
    {
        return response()->json(
            AutorisationSortie::where('parent_id', $request->user()->id)
                ->with('etudiant:id,nom,prenom')
                ->orderByDesc('date_sortie')->orderByDesc('id')
                ->limit(100)->get()
        );
    }

    public function signer(Request $request, Etudiant $etudiant): JsonResponse
    {
        $parent = $request->user();
        $this->assertEnfant($parent, $etudiant);

        $data = $request->validate([
            'date_sortie' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'heure_sortie' => ['required', 'date_format:H:i'],
            'motif' => ['required', 'string', 'min:3', 'max:255'],
            'accompagnant' => ['nullable', 'string', 'max:150'],
            'signature_nom' => ['required', 'string', 'min:3', 'max:150'],
            'attestation' => ['accepted'],
        ], [
            'attestation.accepted' => 'Cochez la case pour signer l’autorisation.',
            'date_sortie.after_or_equal' => 'La date de sortie ne peut pas être passée.',
        ]);

        // La signature doit reprendre le nom du compte parent : on évite qu'un tiers signe à sa place sans le dire.
        if (! $this->memeNom($data['signature_nom'], $parent->name)) {
            return response()->json([
                'message' => 'Le nom de signature doit être celui du titulaire du compte.',
                'errors' => ['signature_nom' => ["Tapez votre nom tel qu'il figure sur votre compte : {$parent->name}."]],
            ], 422);
        }

        $contenu = [
            'etudiant_id' => $etudiant->id,
            'parent_id' => $parent->id,
            'date_sortie' => $data['date_sortie'],
            'heure_sortie' => $data['heure_sortie'],
            'motif' => trim($data['motif']),
            'accompagnant' => isset($data['accompagnant']) ? trim($data['accompagnant']) : null,
            'signature_nom' => trim($data['signature_nom']),
            'signe_le' => now()->startOfSecond()->toIso8601String(),
        ];

        $autorisation = AutorisationSortie::create($contenu + [
            'etablissement_id' => $etudiant->etablissement_id,
            'signe_le' => \Carbon\Carbon::parse($contenu['signe_le']),
            'signature_ip' => $request->ip(),
            'signature_empreinte' => AutorisationSortie::empreinte($contenu),
            'statut' => 'en_attente',
        ]);

        AuditLogger::log($parent, 'signature_sortie', 'autorisations_sortie', $autorisation->id,
            "Autorisation de sortie signée pour {$etudiant->prenom} {$etudiant->nom} le {$data['date_sortie']} à {$data['heure_sortie']}");

        return response()->json([
            'message' => 'Autorisation signée et transmise à l’établissement.',
            'autorisation' => $autorisation->load('etudiant:id,nom,prenom'),
        ], 201);
    }

    public function annuler(Request $request, AutorisationSortie $autorisation): JsonResponse
    {
        abort_unless($autorisation->parent_id === $request->user()->id, 403, 'Cette autorisation ne vous appartient pas.');
        abort_unless(in_array($autorisation->statut, ['en_attente', 'validee'], true), 422, 'Cette autorisation ne peut plus être annulée.');

        $autorisation->update(['statut' => 'annulee']);
        AuditLogger::log($request->user(), 'annulation_sortie', 'autorisations_sortie', $autorisation->id, 'Autorisation de sortie annulée par le parent');

        return response()->json(['message' => 'Autorisation annulée.', 'autorisation' => $autorisation]);
    }

    public function preferences(Request $request): JsonResponse
    {
        return response()->json([
            'preferences' => $request->user()->preferencesNotifications(),
            'evenements' => User::EVENEMENTS_NOTIFIES,
            'canaux' => User::CANAUX_NOTIFICATION,
        ]);
    }

    public function enregistrerPreferences(Request $request): JsonResponse
    {
        $regles = ['preferences' => ['required', 'array']];
        foreach (User::EVENEMENTS_NOTIFIES as $evenement) {
            foreach (User::CANAUX_NOTIFICATION as $canal) {
                $regles["preferences.{$evenement}.{$canal}"] = ['sometimes', 'boolean'];
            }
        }
        $data = $request->validate($regles);

        $user = $request->user();
        $actuelles = $user->preferencesNotifications();
        foreach (User::EVENEMENTS_NOTIFIES as $evenement) {
            foreach (User::CANAUX_NOTIFICATION as $canal) {
                if (isset($data['preferences'][$evenement][$canal])) {
                    $actuelles[$evenement][$canal] = (bool) $data['preferences'][$evenement][$canal];
                }
            }
        }
        $user->forceFill(['preferences_notifications' => $actuelles])->save();

        return response()->json(['message' => 'Préférences enregistrées.', 'preferences' => $user->preferencesNotifications()]);
    }

    // ---------------------------------------------------------------- Établissement

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'statut' => ['nullable', Rule::in(AutorisationSortie::STATUTS)],
        ]);

        $liste = AutorisationSortie::query()
            ->with(['etudiant:id,nom,prenom,matricule,classe_id', 'etudiant.classe:id,nom', 'parent:id,name,telephone', 'traitePar:id,name'])
            ->when(\App\Services\TeacherScope::classeIds($request->user()), fn ($q, $ids) => $q->whereHas('etudiant', fn ($e) => $e->whereIn('classe_id', $ids)))
            ->when(\App\Services\TeacherScope::aUnPerimetre($request->user()) && \App\Services\TeacherScope::classeIds($request->user()) === [], fn ($q) => $q->whereRaw('1 = 0'))
            ->when($request->query('date'), fn ($q, $d) => $q->whereDate('date_sortie', $d))
            ->when($request->query('statut'), fn ($q, $s) => $q->where('statut', $s))
            ->orderBy('date_sortie')->orderBy('heure_sortie')
            ->paginate(min(100, max(1, (int) $request->query('per_page', 30))));

        $liste->getCollection()->transform(function (AutorisationSortie $a) {
            $a->setAttribute('signature_intacte', $a->empreinteValide());

            return $a;
        });

        return response()->json($liste);
    }

    public function decider(Request $request, AutorisationSortie $autorisation): JsonResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['validee', 'refusee'])],
            'commentaire' => ['nullable', 'string', 'max:255', 'required_if:decision,refusee'],
        ], ['commentaire.required_if' => 'Indiquez au parent la raison du refus.']);

        $this->assertPerimetre($request, $autorisation);
        abort_unless($autorisation->statut === 'en_attente', 422, 'Cette autorisation a déjà été traitée.');

        $autorisation->update([
            'statut' => $data['decision'],
            'commentaire' => $data['commentaire'] ?? null,
            'traite_par' => $request->user()->id,
            'traite_le' => now(),
        ]);

        AuditLogger::log($request->user(), 'decision_sortie', 'autorisations_sortie', $autorisation->id,
            $data['decision'] === 'validee' ? 'Autorisation de sortie validée' : 'Autorisation de sortie refusée');

        return response()->json(['message' => 'Décision enregistrée.', 'autorisation' => $autorisation->fresh(['etudiant:id,nom,prenom', 'traitePar:id,name'])]);
    }

    public function marquerSortie(Request $request, AutorisationSortie $autorisation, NotificationParentsService $notifications): JsonResponse
    {
        $this->assertPerimetre($request, $autorisation);
        abort_unless($autorisation->statut === 'validee', 422, 'Seule une autorisation validée permet la sortie.');
        abort_unless($autorisation->empreinteValide(), 422, 'La signature de cette autorisation ne correspond plus à son contenu.');

        $autorisation->update(['statut' => 'effectuee', 'sortie_le' => now()]);

        AuditLogger::log($request->user(), 'sortie_effectuee', 'autorisations_sortie', $autorisation->id, 'Sortie de l’élève constatée');

        try {
            $notifications->notifierSortie($autorisation->etudiant, $autorisation);
        } catch (\Throwable $e) {
            // La sortie est enregistrée même si la notification échoue.
        }

        return response()->json(['message' => 'Sortie enregistrée, les parents sont prévenus.', 'autorisation' => $autorisation]);
    }

    /** Document imprimable (parent signataire ou personnel de l'établissement). */
    public function document(Request $request, AutorisationSortie $autorisation)
    {
        $user = $request->user();
        $estParent = $user->role === 'parent';
        abort_if($estParent && $autorisation->parent_id !== $user->id, 403, 'Cette autorisation ne vous appartient pas.');
        abort_unless($estParent || $user->hasAnyRole(explode(',', self::ROLES_ECOLE)), 403);

        $autorisation->load(['etudiant.classe:id,nom', 'parent:id,name,telephone', 'traitePar:id,name']);
        $identite = IdentiteEtablissement::pour(Etablissement::find($autorisation->etablissement_id));
        $statuts = [
            'en_attente' => 'En attente de validation', 'validee' => 'Validée', 'refusee' => 'Refusée',
            'effectuee' => 'Sortie effectuée', 'annulee' => 'Annulée',
        ];

        $html = view('documents.autorisation-sortie', [
            'a' => $autorisation,
            'numero' => 'AS-'.str_pad((string) $autorisation->id, 6, '0', STR_PAD_LEFT),
            'ecole' => $identite,
            'logo' => $identite['logo'],
            'couleur' => $identite['couleur'],
            'teinte' => $identite['teinte'],
            'statut' => $statuts[$autorisation->statut] ?? $autorisation->statut,
            'intacte' => $autorisation->empreinteValide(),
            'emisLe' => now()->format('d/m/Y à H:i'),
        ])->render();

        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    private function assertPerimetre(Request $request, AutorisationSortie $autorisation): void
    {
        $classe = (int) Etudiant::whereKey($autorisation->etudiant_id)->value('classe_id');
        abort_unless(\App\Services\TeacherScope::canAccessClasse($request->user(), $classe), 403, 'Cet élève est hors de votre périmètre.');
    }

    private function assertEnfant(User $parent, Etudiant $etudiant): void
    {
        $lie = $parent->enfants()->where('etudiants.id', $etudiant->id)
            ->where('etudiants.etablissement_id', $parent->etablissement_id)
            ->exists();

        abort_unless($lie, 403, 'Vous ne pouvez signer que pour vos enfants.');
    }

    /** Compare deux noms sans tenir compte des majuscules, accents, espaces multiples ni de l'ordre des mots. */
    private function memeNom(string $a, string $b): bool
    {
        $normaliser = function (string $s): string {
            $s = \Illuminate\Support\Str::ascii(mb_strtolower(trim($s)));
            $mots = preg_split('/[\s\-]+/', $s, -1, PREG_SPLIT_NO_EMPTY);
            sort($mots);

            return implode(' ', $mots);
        };

        return $normaliser($a) === $normaliser($b);
    }
}
