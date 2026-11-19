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

        // Verrouillage : cinq mots de passe faux bloquent le compte 15 minutes, même avec le bon mot de passe.
        $cleEchecs = 'connexion-echecs:'.\Illuminate\Support\Str::lower((string) $request->validated('email'));
        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($cleEchecs, 5)) {
            return response()->json([
                'message' => 'Trop de tentatives. Réessayez dans 15 minutes, ou utilisez « Mot de passe oublié ».',
                'code' => 'compte_verrouille',
            ], 429);
        }

        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            \Illuminate\Support\Facades\RateLimiter::hit($cleEchecs, 900);
            throw ValidationException::withMessages([
                'email' => ['Adresse e-mail ou mot de passe incorrect.'],
            ]);
        }

        \Illuminate\Support\Facades\RateLimiter::clear($cleEchecs);

        // Double authentification : sans code, on demande le code ; avec un code invalide, on refuse.
        if ($user->two_factor_confirmed_at) {
            $code = (string) $request->input('code', '');
            if ($code === '') {
                return response()->json(['requires_2fa' => true], 202);
            }

            if (! \App\Services\Totp::verifier(\Illuminate\Support\Facades\Crypt::decryptString($user->two_factor_secret), $code)) {
                throw ValidationException::withMessages(['code' => ['Code invalide ou expiré.']]);
            }
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
