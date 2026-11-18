<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupportDemande;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Support prioritaire : l'établissement envoie une demande, la plateforme y répond. */
class SupportController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin,proviseur');
    }

    public function index(Request $request): JsonResponse
    {
        $demandes = SupportDemande::where('etablissement_id', $request->user()->etablissement_id)
            ->latest('id')
            ->get(['id', 'sujet', 'message', 'statut', 'reponse', 'created_at']);

        return response()->json($demandes);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sujet' => 'required|string|max:150',
            'message' => 'required|string|max:3000',
        ]);

        $demande = SupportDemande::create($data + [
            'etablissement_id' => $request->user()->etablissement_id,
            'user_id' => $request->user()->id,
            'statut' => 'ouverte',
        ]);

        AuditLogger::log($request->user(), 'demande_support', 'SupportDemande', $demande->id, "Demande de support : {$data['sujet']}");

        return response()->json(['message' => 'Votre demande a été transmise au support prioritaire.', 'demande' => $demande], 201);
    }
}
