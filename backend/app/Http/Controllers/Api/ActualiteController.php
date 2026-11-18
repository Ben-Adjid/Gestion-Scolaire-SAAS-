<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Actualite;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Fil d'actualité de l'établissement (formule VIP et plus). */
class ActualiteController extends Controller
{
    public function index(): JsonResponse
    {
        $actualites = Actualite::with('auteur:id,name')->latest('id')->limit(50)->get();

        return response()->json($actualites);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'titre' => 'required|string|max:150',
            'contenu' => 'required|string|max:3000',
        ]);

        $actualite = Actualite::create($data + [
            'etablissement_id' => $request->user()->etablissement_id,
            'auteur_id' => $request->user()->id,
        ]);

        AuditLogger::log($request->user(), 'publication_actualite', 'actualites', $actualite->id, "Publication : {$data['titre']}");

        return response()->json(['message' => 'Publication diffusée.', 'actualite' => $actualite->load('auteur:id,name')], 201);
    }

    public function destroy(Request $request, Actualite $actualite): JsonResponse
    {
        $actualite->delete();

        AuditLogger::log($request->user(), 'suppression_actualite', 'actualites', $actualite->id, 'Publication supprimée');

        return response()->json(['message' => 'Publication supprimée.']);
    }
}
