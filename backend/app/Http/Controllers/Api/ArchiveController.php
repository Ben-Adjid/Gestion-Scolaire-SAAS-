<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ArchiveAnnee;
use App\Services\ArchivageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Archives annuelles scellées (formule Entreprise). Aucune modification ni suppression :
 * une archive se crée, se consulte et se vérifie.
 */
class ArchiveController extends Controller
{
    public function index(): JsonResponse
    {
        $archives = ArchiveAnnee::with('auteur:id,name')->latest('id')->get(['id', 'annee_scolaire', 'empreinte', 'archive_par', 'created_at']);

        return response()->json($archives);
    }

    public function store(Request $request, ArchivageService $archivage): JsonResponse
    {
        $data = $request->validate(['annee_scolaire' => 'required|string|max:20|regex:/^\d{4}-\d{4}$/']);

        abort_if(
            ArchiveAnnee::where('annee_scolaire', $data['annee_scolaire'])->exists(),
            409,
            "L'année {$data['annee_scolaire']} est déjà archivée."
        );

        $archive = $archivage->sceller((int) $request->user()->etablissement_id, $data['annee_scolaire'], $request->user());

        return response()->json(['message' => "Année {$archive->annee_scolaire} archivée et scellée.", 'archive' => $archive->only(['id', 'annee_scolaire', 'empreinte'])], 201);
    }

    public function show(ArchiveAnnee $archive): JsonResponse
    {
        return response()->json([
            'id' => $archive->id,
            'annee_scolaire' => $archive->annee_scolaire,
            'empreinte' => $archive->empreinte,
            'created_at' => $archive->created_at,
            'donnees' => json_decode($archive->donnees, true),
        ]);
    }

    public function verifier(ArchiveAnnee $archive, ArchivageService $archivage): JsonResponse
    {
        return response()->json([
            'id' => $archive->id,
            'annee_scolaire' => $archive->annee_scolaire,
            'intacte' => $archivage->estIntacte($archive),
        ]);
    }
}
