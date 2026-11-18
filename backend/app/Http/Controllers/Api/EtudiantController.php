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
        $query = Etudiant::query()->with('classe:id,nom,niveau');

        $classeIds = TeacherScope::classeIds($request->user());
        if ($classeIds !== null) {
            $query->whereIn('classe_id', $classeIds);
        }

        if ($request->classe_id) {
            $query->where('classe_id', $request->classe_id);
        }

        if ($request->search) {
            $search = $request->search;
            $query->where(fn ($q) => $q->where('nom', 'like', "%{$search}%")
                ->orWhere('prenom', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"));
        }

        return response()->json($query->paginate(20));
    }

    public function store(EtudiantRequest $request)
    {
        $data = $request->validated();
        $confirmerDoublon = $data['confirmer_doublon'] ?? false;
        unset($data['confirmer_doublon']);

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

        $etudiant = Etudiant::create($data);

        AuditLogger::log(auth()->user(), 'create', 'Etudiant', $etudiant->id, "Nouvel étudiant : {$etudiant->nom} {$etudiant->prenom}");
        if ($doublons->isNotEmpty()) {
            AuditLogger::log(auth()->user(), 'creation_doublon_confirmee', 'Etudiant', $etudiant->id,
                "Création confirmée malgré un doublon potentiel.");
        }

        return response()->json($etudiant, 201);
    }

    public function lookup(Request $request)
    {
        $matricule = $request->query('matricule');

        if (!$matricule) {
            return response()->json(['message' => 'Matricule required'], 400);
        }

        $etudiant = Etudiant::where('matricule', $matricule)->first();

        if (!$etudiant) {
            return response()->json(['message' => 'Étudiant not found'], 404);
        }

        $this->assertPeutVoir($request->user(), $etudiant);

        $bulletins = app(BulletinService::class);
        $moyenne = $bulletins->moyenneAnnuelle($etudiant);

        return response()->json([
            'etudiant' => $etudiant->load('classe'),
            'dossier' => $etudiant->dossierInscription,
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
        $etudiant->update($data);

        AuditLogger::log(auth()->user(), 'update', 'Etudiant', $etudiant->id, "Mise à jour de l'étudiant");

        return response()->json($etudiant);
    }

    public function destroy(Etudiant $etudiant)
    {
        AuditLogger::log(auth()->user(), 'delete', 'Etudiant', $etudiant->id, "Suppression de l'étudiant");

        $etudiant->delete();

        return response()->json(null, 204);
    }
}
