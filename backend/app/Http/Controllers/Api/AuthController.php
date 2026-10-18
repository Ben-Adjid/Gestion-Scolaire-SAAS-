<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Les identifiants fournis sont incorrects.'],
            ]);
        }

        if (! $user->isSuperAdmin() && (! $user->etablissement || ! $user->etablissement->isActif())) {
            return response()->json([
                'message' => "L'abonnement de votre établissement n'est pas actif.",
                'code' => 'subscription_inactive',
            ], 403);
        }

        AuditLogger::log($user, 'connexion', 'authentification', null, "Connexion de {$user->name} ({$user->role}).");

        return response()->json([
            'token' => $user->createToken('auth-token')->plainTextToken,
            'user' => $user,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        AuditLogger::log($user, 'déconnexion', 'authentification', null, "Déconnexion de {$user?->name}.");

        $token = $request->user()->currentAccessToken();

        if ($token instanceof \Laravel\Sanctum\PersonalAccessToken) {
            $token->delete();
        }

        return response()->json(['message' => 'Déconnexion réussie.']);
    }
}
