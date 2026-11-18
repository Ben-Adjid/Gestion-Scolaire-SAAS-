<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Enseignant;
use App\Models\Etudiant;
use App\Models\RendezVous;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\NotificationParentsService;
use App\Services\TeacherScope;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Rendez-vous parents-professeurs.
 * Le parent choisit un enseignant de la classe de son enfant et propose jusqu'à trois créneaux ;
 * l'enseignant (ou la direction / vie scolaire) confirme un créneau, en propose un autre, ou refuse.
 */
class RendezVousController extends Controller
{
    private const ROLES_ECOLE = 'enseignant,admin,proviseur,censeur,cpe';

    public function __construct()
    {
        $this->middleware('role:parent')->only(['mesRendezVous', 'enseignantsDeLEnfant', 'demander', 'annuler']);
        $this->middleware('role:'.self::ROLES_ECOLE)->only(['index', 'confirmer', 'refuser']);
    }

    // ---------------------------------------------------------------- Parent

    public function mesRendezVous(Request $request): JsonResponse
    {
        return response()->json(
            RendezVous::where('parent_id', $request->user()->id)
                ->with(['etudiant:id,nom,prenom', 'enseignant:id,nom,prenom'])
                ->orderByDesc('id')->limit(100)->get()
        );
    }

    /** Enseignants de la classe de l'enfant, avec les matières qu'ils enseignent. */
    public function enseignantsDeLEnfant(Request $request, Etudiant $etudiant): JsonResponse
    {
        $this->assertEnfant($request->user(), $etudiant);

        return response()->json(
            $this->enseignantsDeClasse((int) $etudiant->classe_id)
                ->map(fn (Enseignant $e) => [
                    'id' => $e->id,
                    'nom' => trim("{$e->prenom} {$e->nom}"),
                    'matieres' => $e->matieres->pluck('nom')->values(),
                ])->values()
        );
    }

    public function demander(Request $request, Etudiant $etudiant): JsonResponse
    {
        $parent = $request->user();
        $this->assertEnfant($parent, $etudiant);

        $data = $request->validate([
            'enseignant_id' => ['required', 'integer'],
            'motif' => ['required', 'string', 'min:5', 'max:500'],
            'creneaux' => ['required', 'array', 'min:1', 'max:3'],
            'creneaux.*' => ['required', 'date_format:Y-m-d\TH:i', 'after:now'],
        ], [
            'creneaux.*.after' => 'Les créneaux proposés doivent être dans le futur.',
            'creneaux.required' => 'Proposez au moins un créneau.',
        ]);

        abort_unless(
            $this->enseignantsDeClasse((int) $etudiant->classe_id)->contains('id', (int) $data['enseignant_id']),
            422,
            'Cet enseignant n’intervient pas dans la classe de votre enfant.'
        );

        $creneaux = collect($data['creneaux'])->unique()->sort()->values()->all();
        $rdv = RendezVous::create([
            'etablissement_id' => $etudiant->etablissement_id,
            'etudiant_id' => $etudiant->id,
            'parent_id' => $parent->id,
            'enseignant_id' => (int) $data['enseignant_id'],
            'motif' => trim($data['motif']),
            'creneaux_proposes' => $creneaux,
            'statut' => 'demande',
        ]);

        AuditLogger::log($parent, 'demande_rendez_vous', 'rendez_vous', $rdv->id,
            "Demande de rendez-vous pour {$etudiant->prenom} {$etudiant->nom}");

        return response()->json([
            'message' => 'Demande envoyée à l’enseignant.',
            'rendez_vous' => $rdv->load(['etudiant:id,nom,prenom', 'enseignant:id,nom,prenom']),
        ], 201);
    }

    public function annuler(Request $request, RendezVous $rendezVous): JsonResponse
    {
        abort_unless($rendezVous->parent_id === $request->user()->id, 403, 'Ce rendez-vous ne vous appartient pas.');
        abort_unless(in_array($rendezVous->statut, ['demande', 'confirme'], true), 422, 'Ce rendez-vous ne peut plus être annulé.');

        $rendezVous->update(['statut' => 'annule']);

        return response()->json(['message' => 'Rendez-vous annulé.', 'rendez_vous' => $rendezVous]);
    }

    // ---------------------------------------------------------------- Établissement

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $liste = RendezVous::query()
            ->with(['etudiant:id,nom,prenom,classe_id', 'etudiant.classe:id,nom', 'parent:id,name,telephone,email', 'enseignant:id,nom,prenom'])
            // Un enseignant ne voit que ses rendez-vous ; la direction ceux de son périmètre.
            ->when($user->role === 'enseignant', fn ($q) => $q->where('enseignant_id', $user->enseignant_id ?? 0))
            ->when($user->role !== 'enseignant' && TeacherScope::classeIds($user) !== null,
                fn ($q) => $q->whereHas('etudiant', fn ($e) => $e->whereIn('classe_id', TeacherScope::classeIds($user))))
            ->when($request->query('statut'), fn ($q, $s) => $q->where('statut', $s))
            ->orderByRaw("CASE WHEN statut = 'demande' THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->paginate(min(100, max(1, (int) $request->query('per_page', 30))));

        return response()->json($liste);
    }

    public function confirmer(Request $request, RendezVous $rendezVous, NotificationParentsService $notifications): JsonResponse
    {
        $this->assertPeutTraiter($request->user(), $rendezVous);
        abort_unless($rendezVous->statut === 'demande', 422, 'Ce rendez-vous a déjà été traité.');

        $data = $request->validate([
            'date_retenue' => ['required', 'date_format:Y-m-d\TH:i', 'after:now'],
            'lieu' => ['nullable', 'string', 'max:150'],
            'reponse' => ['nullable', 'string', 'max:500'],
        ]);

        $rendezVous->update([
            'statut' => 'confirme',
            'date_retenue' => Carbon::createFromFormat('Y-m-d\TH:i', $data['date_retenue']),
            'lieu' => $data['lieu'] ?? null,
            'reponse' => $data['reponse'] ?? null,
            'traite_par' => $request->user()->id,
        ]);

        AuditLogger::log($request->user(), 'confirmation_rendez_vous', 'rendez_vous', $rendezVous->id, 'Rendez-vous confirmé');
        $this->prevenir($notifications, $rendezVous);

        return response()->json(['message' => 'Rendez-vous confirmé, le parent est prévenu.', 'rendez_vous' => $rendezVous]);
    }

    public function refuser(Request $request, RendezVous $rendezVous, NotificationParentsService $notifications): JsonResponse
    {
        $this->assertPeutTraiter($request->user(), $rendezVous);
        abort_unless($rendezVous->statut === 'demande', 422, 'Ce rendez-vous a déjà été traité.');

        $data = $request->validate(['reponse' => ['required', 'string', 'min:3', 'max:500']], [
            'reponse.required' => 'Expliquez au parent pourquoi (ou proposez-lui de reprendre contact).',
        ]);

        $rendezVous->update(['statut' => 'refuse', 'reponse' => $data['reponse'], 'traite_par' => $request->user()->id]);

        AuditLogger::log($request->user(), 'refus_rendez_vous', 'rendez_vous', $rendezVous->id, 'Rendez-vous refusé');
        $this->prevenir($notifications, $rendezVous);

        return response()->json(['message' => 'Réponse envoyée au parent.', 'rendez_vous' => $rendezVous]);
    }

    // ---------------------------------------------------------------- Outils

    private function enseignantsDeClasse(int $classeId)
    {
        return Enseignant::whereHas('classes', fn ($q) => $q->where('classes.id', $classeId))
            ->with('matieres:id,nom')
            ->orderBy('nom')
            ->get();
    }

    private function assertEnfant(User $parent, Etudiant $etudiant): void
    {
        $lie = $parent->enfants()->where('etudiants.id', $etudiant->id)
            ->where('etudiants.etablissement_id', $parent->etablissement_id)
            ->exists();

        abort_unless($lie, 403, 'Vous ne pouvez demander un rendez-vous que pour vos enfants.');
    }

    /** L'enseignant concerné, ou la direction / vie scolaire dans son périmètre. */
    private function assertPeutTraiter(User $user, RendezVous $rdv): void
    {
        if ($user->role === 'enseignant') {
            abort_unless((int) $user->enseignant_id === (int) $rdv->enseignant_id, 403, 'Ce rendez-vous concerne un autre enseignant.');

            return;
        }

        $classe = (int) Etudiant::whereKey($rdv->etudiant_id)->value('classe_id');
        abort_unless(TeacherScope::canAccessClasse($user, $classe), 403, 'Cet élève est hors de votre périmètre.');
    }

    private function prevenir(NotificationParentsService $notifications, RendezVous $rdv): void
    {
        try {
            $notifications->notifierRendezVous($rdv->loadMissing(['etudiant', 'enseignant']));
        } catch (\Throwable $e) {
            // La réponse est enregistrée même si la notification échoue ; le parent la voit dans son espace.
        }
    }
}
