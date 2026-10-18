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
        $classe = Classe::create($request->validated());

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
        $classe->update($request->validated());

        AuditLogger::log($request->user(), 'modification', 'classes', $classe->id,
            "Modification de la classe {$classe->nom}");

        return response()->json([
            'message' => 'Classe mise à jour avec succès.',
            'classe' => $classe,
        ]);
    }

    public function destroy(Classe $classe): JsonResponse
    {
        $name = $classe->nom;
        $classe->delete();

        AuditLogger::log(auth()->user(), 'suppression', 'classes', $classe->id,
            "Suppression de la classe $name");

        return response()->json(['message' => 'Classe supprimée avec succès.']);
    }
}
