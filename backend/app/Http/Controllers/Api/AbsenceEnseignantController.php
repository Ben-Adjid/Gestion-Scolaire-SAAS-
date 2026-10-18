<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AbsenceEnseignantRequest;
use App\Models\AbsenceEnseignant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AbsenceEnseignantController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:enseignant')->only(['store']);
        $this->middleware('role:admin,proviseur,censeur')->only(['index', 'stats', 'destroy']);
    }

    public function index(Request $request): JsonResponse
    {
        $absences = AbsenceEnseignant::query()
            ->with('enseignant:id,nom,prenom,specialite,email')
            ->when($request->query('date'), fn ($q, $v) => $q->where('date', $v))
            ->when($request->query('type'), fn ($q, $v) => $q->where('type', $v))
            ->orderByDesc('date')
            ->paginate((int) $request->query('per_page', 15));

        return response()->json($absences);
    }

    public function stats(Request $request): JsonResponse
    {
        $query = AbsenceEnseignant::query()
            ->when($request->query('date'), fn ($q, $v) => $q->where('date', $v));

        return response()->json([
            'absences' => (clone $query)->where('type', 'absence')->count(),
            'retards' => (clone $query)->where('type', 'retard')->count(),
            'non_justifiees' => (clone $query)->where('justifie', false)->count(),
        ]);
    }

    public function store(AbsenceEnseignantRequest $request): JsonResponse
    {
        $enseignantId = $request->user()->enseignant_id;

        if (! $enseignantId) {
            return response()->json(['message' => "Votre compte n'est pas lié à un enseignant."], 422);
        }

        $data = $request->validated();
        $data['enseignant_id'] = $enseignantId;

        $absence = AbsenceEnseignant::create($data);

        return response()->json([
            'message' => $data['type'] === 'retard'
                ? 'Votre retard a été signalé.'
                : 'Votre absence a été signalée.',
            'absence' => $absence,
        ], 201);
    }

    public function destroy(AbsenceEnseignant $absence): JsonResponse
    {
        $absence->delete();

        return response()->json(['message' => "Signalement supprimé avec succès."]);
    }
}
