<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\Totp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;

/**
 * Double authentification du compte connecté.
 * Activation en deux temps : on génère un secret, puis l'utilisateur confirme avec un code valide.
 */
class TwoFactorController extends Controller
{
    public function statut(Request $request): JsonResponse
    {
        return response()->json(['actif' => (bool) $request->user()->two_factor_confirmed_at]);
    }

    public function activer(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user->two_factor_confirmed_at, 409, 'La double authentification est déjà active.');

        $secret = Totp::genererSecret();
        $user->forceFill(['two_factor_secret' => Crypt::encryptString($secret), 'two_factor_confirmed_at' => null])->save();

        return response()->json([
            'secret' => $secret,
            'uri' => Totp::uri($secret, 'Espace scolaire', $user->email),
            'message' => 'Ajoutez ce compte dans votre application d’authentification, puis saisissez le code affiché.',
        ]);
    }

    public function confirmer(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->two_factor_secret, 422, 'Commencez par générer un secret.');

        $code = (string) $request->validate(['code' => 'required|string|max:10'])['code'];
        if (! Totp::verifier(Crypt::decryptString($user->two_factor_secret), $code)) {
            throw ValidationException::withMessages(['code' => ['Code invalide ou expiré.']]);
        }

        $user->forceFill(['two_factor_confirmed_at' => now()])->save();
        AuditLogger::log($user, 'activation_2fa', 'authentification', $user->id, 'Double authentification activée.');

        return response()->json(['message' => 'Double authentification activée.']);
    }

    public function desactiver(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->two_factor_confirmed_at, 409, 'La double authentification n’est pas active.');

        $code = (string) $request->validate(['code' => 'required|string|max:10'])['code'];
        if (! Totp::verifier(Crypt::decryptString($user->two_factor_secret), $code)) {
            throw ValidationException::withMessages(['code' => ['Code invalide ou expiré.']]);
        }

        $user->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null])->save();
        AuditLogger::log($user, 'desactivation_2fa', 'authentification', $user->id, 'Double authentification désactivée.');

        return response()->json(['message' => 'Double authentification désactivée.']);
    }
}
