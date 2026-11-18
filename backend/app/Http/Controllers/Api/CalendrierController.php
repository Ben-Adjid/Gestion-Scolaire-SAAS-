<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EvenementCalendrier;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Calendrier scolaire : consultation pour toute la communauté, saisie pour la direction. */
class CalendrierController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(EvenementCalendrier::orderBy('date_debut')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'titre' => 'required|string|max:150',
            'type' => ['required', Rule::in(EvenementCalendrier::TYPES)],
            'date_debut' => 'required|date',
            'date_fin' => 'nullable|date|after_or_equal:date_debut',
            'description' => 'nullable|string|max:1000',
        ]);

        $evenement = EvenementCalendrier::create($data + ['etablissement_id' => $request->user()->etablissement_id]);

        AuditLogger::log($request->user(), 'calendrier', 'evenements_calendrier', $evenement->id, "Événement « {$data['titre']} » ajouté au calendrier.");

        return response()->json(['message' => 'Événement ajouté au calendrier.', 'evenement' => $evenement], 201);
    }

    public function destroy(Request $request, EvenementCalendrier $evenement): JsonResponse
    {
        $evenement->delete();

        AuditLogger::log($request->user(), 'calendrier', 'evenements_calendrier', $evenement->id, 'Événement retiré du calendrier.');

        return response()->json(['message' => 'Événement retiré.']);
    }
}
