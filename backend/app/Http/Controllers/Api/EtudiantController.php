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
        $this->middleware('role:admin,censeur,proviseur')->except(['index', 'show', 'lookup']);
        $this->middleware('role:admin,proviseur,censeur,cpe,enseignant,secretaire,gestionnaire,agent_comptable')->only(['index', 'show', 'lookup']);
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
        $etudiant = Etudiant::create($request->validated());

        AuditLogger::log(auth()->user(), 'create', 'Etudiant', $etudiant->id, "Nouvel étudiant : {$etudiant->nom} {$etudiant->prenom}");

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
        $etudiant->update($request->validated());

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
