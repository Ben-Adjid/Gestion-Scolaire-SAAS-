<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExamenPlanning;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Planning des examens (formule VIP et plus) : visible par l'équipe et par les parents. */
class ExamenPlanningController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $epreuves = ExamenPlanning::with(['classe:id,nom', 'matiere:id,nom,couleur'])
            ->when($request->filled('classe_id'), fn ($q) => $q->where('classe_id', (int) $request->query('classe_id')))
            ->orderBy('date')->orderBy('heure_debut')
            ->get();

        return response()->json($epreuves);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'classe_id' => 'required|integer|exists:classes,id',
            'matiere_id' => 'required|integer|exists:matieres,id',
            'date' => 'required|date',
            'heure_debut' => 'required|date_format:H:i',
            'duree_minutes' => 'required|integer|min:30|max:480',
            'salle' => 'nullable|string|max:60',
        ]);

        $conflit = ExamenPlanning::where('classe_id', $data['classe_id'])
            ->whereDate('date', $data['date'])
            ->whereTime('heure_debut', $data['heure_debut'].':00')
            ->exists();

        if ($conflit) {
            return response()->json(['message' => 'Cette classe a déjà une épreuve à cette heure.'], 422);
        }

        $epreuve = ExamenPlanning::create($data);

        AuditLogger::log($request->user(), 'planning_examen', 'examens_planning', $epreuve->id, "Épreuve planifiée le {$data['date']}");

        return response()->json(['message' => 'Épreuve planifiée.', 'epreuve' => $epreuve->load(['classe:id,nom', 'matiere:id,nom,couleur'])], 201);
    }

    public function destroy(Request $request, ExamenPlanning $epreuve): JsonResponse
    {
        $epreuve->delete();

        AuditLogger::log($request->user(), 'suppression_planning_examen', 'examens_planning', $epreuve->id, 'Épreuve supprimée');

        return response()->json(['message' => 'Épreuve supprimée.']);
    }
}
