<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Clés d'accès à l'API (formule Entreprise). Chaque clé appartient à un compte administrateur
 * et ne peut être révoquée que par son propriétaire.
 */
class ApiTokenController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin');
    }

    public function index(Request $request): JsonResponse
    {
        $cles = $request->user()->tokens()
            ->latest('id')
            ->get(['id', 'name', 'last_used_at', 'created_at']);

        return response()->json($cles);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => 'required|string|max:100']);

        // La clé en clair n'est renvoyée qu'une seule fois ; seule son empreinte est conservée.
        $cle = $request->user()->createToken($data['name']);

        AuditLogger::log($request->user(), 'creation_cle_api', 'users', $request->user()->id, "Clé API « {$data['name']} » créée.");

        return response()->json([
            'message' => 'Clé créée. Copiez-la maintenant : elle ne sera plus affichée.',
            'token' => $cle->plainTextToken,
            'cle' => ['id' => $cle->accessToken->id, 'name' => $data['name']],
        ], 201);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $cle = $request->user()->tokens()->whereKey($id)->firstOrFail();
        $nom = $cle->name;
        $cle->delete();

        AuditLogger::log($request->user(), 'revocation_cle_api', 'users', $request->user()->id, "Clé API « {$nom} » révoquée.");

        return response()->json(['message' => 'Clé révoquée.']);
    }
}
