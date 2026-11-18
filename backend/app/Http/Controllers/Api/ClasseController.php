<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClasseRequest;
use App\Models\Classe;
use App\Services\AuditLogger;
use App\Services\TeacherScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClasseController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin,censeur')->only(['store', 'update', 'destroy']);
    }

    public function index(Request $request): JsonResponse
    {
        $query = Classe::with('enseignants:id,nom,prenom')->withCount('etudiants');

        $classeIds = TeacherScope::classeIds($request->user());
        if ($classeIds !== null) {
            $query->whereIn('id', $classeIds);
        }

        if ($request->query('niveau')) {
            $query->where('niveau', $request->query('niveau'));
        }

        if ($request->query('cycle')) {
            $query->where('cycle', $request->query('cycle'));
        }

        $classes = $query->paginate((int) $request->query('per_page', 20));

        return response()->json($classes);
    }

    public function store(ClasseRequest $request): JsonResponse
    {
        $classe = Classe::create(array_filter($request->validated(), fn ($v, $k) => $k !== 'effectif_max' || $v !== null, ARRAY_FILTER_USE_BOTH));

        AuditLogger::log($request->user(), 'création', 'classes', $classe->id,
            "Création de la classe {$classe->nom}");

        return response()->json([
            'message' => 'Classe créée avec succès.',
            'classe' => $classe->load(['enseignants', 'etudiants']),
        ], 201);
    }

    public function show(Classe $classe): JsonResponse
    {
        return response()->json($classe->load(['enseignants', 'etudiants', 'notes']));
    }

    public function update(ClasseRequest $request, Classe $classe): JsonResponse
    {
        // Le niveau détermine la règle de passage des élèves déjà rattachés : il ne change plus une fois des élèves inscrits.
        if ($request->validated('niveau') !== $classe->niveau && $classe->etudiants()->exists()) {
            return response()->json([
                'message' => 'Des élèves sont rattachés à cette classe : son niveau ne peut plus être changé. Créez une nouvelle classe.',
                'errors' => ['niveau' => ['Le niveau ne peut pas changer tant que la classe a des élèves.']],
            ], 422);
        }

        $classe->update(array_filter($request->validated(), fn ($v, $k) => $k !== 'effectif_max' || $v !== null, ARRAY_FILTER_USE_BOTH));

        AuditLogger::log($request->user(), 'modification', 'classes', $classe->id,
            "Modification de la classe {$classe->nom}");

        return response()->json([
            'message' => 'Classe mise à jour avec succès.',
            'classe' => $classe,
        ]);
    }

    public function destroy(Classe $classe): JsonResponse
    {
        if ($classe->etudiants()->exists() || $classe->inscriptions()->exists()) {
            return response()->json([
                'message' => 'Cette classe a des élèves ou des inscriptions : déplacez-les vers une autre classe avant de la supprimer.',
            ], 422);
        }

        $name = $classe->nom;
        $classe->delete();

        AuditLogger::log(auth()->user(), 'suppression', 'classes', $classe->id,
            "Suppression de la classe $name");

        return response()->json(['message' => 'Classe supprimée avec succès.']);
    }
}
