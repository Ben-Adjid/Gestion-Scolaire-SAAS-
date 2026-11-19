<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Etudiant;
use App\Http\Requests\EtudiantRequest;
use Illuminate\Http\Request;
use App\Services\AuditLogger;
use App\Services\BulletinService;
use App\Services\TeacherScope;

class EtudiantController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('role:admin,censeur,proviseur,secretaire')->except(['index', 'show', 'lookup', 'doublons']);
        $this->middleware('role:admin,proviseur,censeur,secretaire')->only(['doublons']);
        $this->middleware('role:admin,proviseur,censeur,cpe,surveillant,enseignant,secretaire,gestionnaire,agent_comptable')->only(['index', 'show', 'lookup']);
    }

    public function index(Request $request)
    {
        // « a_historique » : l'élève a des notes validées ou des frais encaissés. Il ne peut alors plus être supprimé.
        $query = Etudiant::query()->with('classe:id,nom,niveau')
            ->withExists(['notes as a_notes_validees' => fn ($q) => $q->where('statut', 'valide')])
            ->withExists(['fraisScolaires as a_paiements' => fn ($q) => $q->whereHas('paiements', fn ($p) => $p->where('statut', 'completed'))]);

        $classeIds = TeacherScope::classeIds($request->user());
        if ($classeIds !== null) {
            $query->whereIn('classe_id', $classeIds);
        }

        if ($request->classe_id) {
            $query->where('classe_id', $request->classe_id);
        }

        // Recherche par nom, prénom, matricule ou e-mail (insensible à la casse, les espaces sont ignorés aux extrémités).
        $recherche = trim((string) $request->query('search', ''));
        if ($recherche !== '') {
            $query->where(fn ($q) => $q->where('nom', 'like', "%{$recherche}%")
                ->orWhere('prenom', 'like', "%{$recherche}%")
                ->orWhere('matricule', 'like', "%{$recherche}%")
                ->orWhere('email', 'like', "%{$recherche}%"));
        }

        // Statut : actif = inscrit pour l'année en cours de l'école ; inactif = pas d'inscription cette année.
        $statut = $request->query('statut');
        if (in_array($statut, ['actif', 'inactif'], true)) {
            $annee = $request->user()->etablissement?->annee_scolaire;
            $inscrit = fn ($q) => $q->where('statut', 'active')->when($annee, fn ($i) => $i->where('annee_scolaire', $annee));
            $statut === 'actif'
                ? $query->whereHas('inscriptions', $inscrit)
                : $query->whereDoesntHave('inscriptions', $inscrit);
        }

        return response()->json($query->paginate(20));
    }

    public function store(EtudiantRequest $request)
    {
        $data = $request->validated();
        $confirmerDoublon = $data['confirmer_doublon'] ?? false;
        $parent = $data['parent'] ?? null;
        unset($data['confirmer_doublon'], $data['parent']);

        $doublons = $this->trouverDoublons($data);
        if ($doublons->isNotEmpty() && ! $confirmerDoublon) {
            return response()->json([
                'message' => 'Un ou plusieurs élèves avec une identité proche existent déjà.',
                'code' => 'doublon_potentiel',
                'doublons' => $doublons->map(fn (Etudiant $eleve) => [
                    'id' => $eleve->id,
                    'nom' => $eleve->nom,
                    'prenom' => $eleve->prenom,
                    'matricule' => $eleve->matricule,
                    'date_naissance' => $eleve->date_naissance?->toDateString(),
                    'classe' => $eleve->classe?->nom,
                ])->values(),
            ], 409);
        }

        [$etudiant, $compteParent] = \Illuminate\Support\Facades\DB::transaction(function () use ($data, $parent) {
            $etudiant = Etudiant::create($data);

            return [$etudiant, app(\App\Services\CompteParentService::class)->rattacher($etudiant, $parent, auth()->user())];
        });

        AuditLogger::log(auth()->user(), 'create', 'Etudiant', $etudiant->id, "Nouvel étudiant : {$etudiant->nom} {$etudiant->prenom}");
        if ($doublons->isNotEmpty()) {
            AuditLogger::log(auth()->user(), 'creation_doublon_confirmee', 'Etudiant', $etudiant->id,
                "Création confirmée malgré un doublon potentiel.");
        }

        return response()->json($etudiant->toArray() + ['compte_parent' => $compteParent], 201);
    }

    /** Élève déjà inscrit : créer (ou relier) le compte de son parent. */
    public function compteParent(Request $request, Etudiant $etudiant, \App\Services\CompteParentService $comptes)
    {
        abort_unless($comptes->disponible($etudiant), 403, 'L’espace parent est inclus dans la formule VIP.');
        $data = $request->validate(\App\Services\CompteParentService::regles() + ['parent' => ['required', 'array']]);

        $resultat = \Illuminate\Support\Facades\DB::transaction(fn () => $comptes->rattacher($etudiant, $data['parent'], $request->user()));

        return response()->json([
            'message' => match ($resultat['statut']) {
                'cree' => 'Compte parent créé et relié à l’élève.',
                'relie' => 'Parent existant relié à l’élève.',
                default => 'Ce parent est déjà relié à l’élève.',
            },
            'compte_parent' => $resultat,
        ], $resultat['statut'] === 'cree' ? 201 : 200);
    }

    /** Parents reliés à l'élève (pour l'agent d'inscription). */
    public function parents(Etudiant $etudiant)
    {
        return response()->json(
            $etudiant->parents()->get(['users.id', 'users.name', 'users.email', 'users.telephone'])
                ->map(fn ($p) => ['id' => $p->id, 'nom' => $p->name, 'email' => $p->email, 'telephone' => $p->telephone, 'lien' => $p->pivot->lien])
        );
    }

    public function lookup(Request $request)
    {
        $matricule = $request->query('matricule');

        if (!$matricule) {
            return response()->json(['message' => 'Indiquez le matricule de l’élève à rechercher.'], 400);
        }

        $etudiant = Etudiant::where('matricule', $matricule)->first();

        if (!$etudiant) {
            return response()->json(['message' => 'Aucun élève ne porte ce matricule. Vérifiez la saisie (ex. LIC-2026-0001).'], 404);
        }

        $this->assertPeutVoir($request->user(), $etudiant);

        $bulletins = app(BulletinService::class);
        $moyenne = $bulletins->moyenneAnnuelle($etudiant);

        return response()->json([
            'etudiant' => $etudiant->load('classe'),
            'dossier' => $etudiant->dossierInscription,
            'annee_reference' => $bulletins->anneeDeReference($etudiant),
            'moyenne_annuelle' => $moyenne,
            'decision' => $bulletins->decision($moyenne),
        ]);
    }

    public function doublons(Request $request)
    {
        $data = $request->validate([
            'nom' => 'required|string|max:100',
            'prenom' => 'required|string|max:100',
            'date_naissance' => 'nullable|date',
            'classe_id' => 'nullable|integer',
        ]);

        return response()->json([
            'doublons' => $this->trouverDoublons($data)
                ->map(fn (Etudiant $eleve) => [
                    'id' => $eleve->id,
                    'nom' => $eleve->nom,
                    'prenom' => $eleve->prenom,
                    'matricule' => $eleve->matricule,
                    'date_naissance' => $eleve->date_naissance?->toDateString(),
                    'classe' => $eleve->classe?->nom,
                ])->values(),
        ]);
    }

    private function trouverDoublons(array $data)
    {
        $nom = mb_strtolower(trim($data['nom']));
        $prenom = mb_strtolower(trim($data['prenom']));

        return Etudiant::query()
            ->with('classe:id,nom')
            ->whereRaw('LOWER(nom) = ?', [$nom])
            ->whereRaw('LOWER(prenom) = ?', [$prenom])
            ->when($data['date_naissance'] ?? null, fn ($query, $date) => $query->whereDate('date_naissance', $date))
            ->when(! ($data['date_naissance'] ?? null) && ($data['classe_id'] ?? null), fn ($query) => $query->where('classe_id', $data['classe_id']))
            ->limit(10)
            ->get();
    }

    public function show(Request $request, Etudiant $etudiant)
    {
        $this->assertPeutVoir($request->user(), $etudiant);

        return response()->json($etudiant->load($this->relationsVisibles($request->user())));
    }

    private function assertPeutVoir($user, Etudiant $etudiant): void
    {
        if (! TeacherScope::canAccessClasse($user, (int) $etudiant->classe_id)) {
            abort(403, "Vous ne pouvez consulter que les élèves de vos classes.");
        }
    }

    private function relationsVisibles($user): array
    {
        return $user->hasAnyRole(['admin', 'proviseur', 'censeur', 'cpe', 'enseignant'])
            ? ['classe', 'absences', 'notes']
            : ['classe'];
    }

    public function update(EtudiantRequest $request, Etudiant $etudiant)
    {
        $data = $request->validated();
        unset($data['confirmer_doublon']);
        // Le matricule est attribué une fois pour toutes : il sert de référence aux familles et aux archives.
        unset($data['matricule']);
        $etudiant->update($data);

        AuditLogger::log(auth()->user(), 'update', 'Etudiant', $etudiant->id, "Mise à jour de l'étudiant");

        return response()->json($etudiant);
    }

    public function destroy(Etudiant $etudiant)
    {
        if ($etudiant->notes()->where('statut', 'valide')->exists() || $etudiant->fraisScolaires()->whereHas('paiements', fn ($p) => $p->where('statut', 'completed'))->exists()) {
            return response()->json([
                'message' => 'Cet élève a des notes validées ou des paiements encaissés : son dossier est conservé pour l’historique et ne peut pas être supprimé.',
            ], 422);
        }

        AuditLogger::log(auth()->user(), 'delete', 'Etudiant', $etudiant->id, "Suppression de l'étudiant");

        $etudiant->delete();

        return response()->json(null, 204);
    }
}
