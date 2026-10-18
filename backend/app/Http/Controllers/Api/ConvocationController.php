<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConvocationRequest;
use App\Models\Convocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConvocationController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin,proviseur,censeur,cpe')->only(['index', 'store', 'destroy']);
    }

    public function index(Request $request): JsonResponse
    {
        $convocations = Convocation::query()
            ->with(['etudiant:id,nom,prenom,matricule,classe_id', 'etudiant.classe:id,nom'])
            ->when($request->query('etudiant_id'), fn ($q, $v) => $q->where('etudiant_id', $v))
            ->when($request->query('classe_id'), fn ($q, $v) => $q->whereHas('etudiant', fn ($q2) => $q2->where('classe_id', $v)))
            ->orderByDesc('date')
            ->paginate((int) $request->query('per_page', 15));

        return response()->json($convocations);
    }

    public function store(ConvocationRequest $request): JsonResponse
    {
        $convocation = Convocation::create($request->validated());

        return response()->json([
            'message' => 'Convocation enregistrée avec succès.',
            'convocation' => $convocation->load('etudiant:id,nom,prenom,matricule,classe_id', 'etudiant.classe:id,nom'),
        ], 201);
    }

    public function destroy(Convocation $convocation): JsonResponse
    {
        $convocation->delete();

        return response()->json(['message' => 'Convocation supprimée avec succès.']);
    }
}
