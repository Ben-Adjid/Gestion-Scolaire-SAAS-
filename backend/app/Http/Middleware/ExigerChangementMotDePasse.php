<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Tant que le mot de passe temporaire n'est pas changé, l'utilisateur ne peut utiliser que
 * son profil, la déconnexion, le changement de mot de passe et la double authentification.
 */
class ExigerChangementMotDePasse
{
    private const AUTORISEES = ['api/me', 'api/logout', 'api/mot-de-passe/changer', 'api/2fa/*'];

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && $user->doit_changer_mot_de_passe && ! $request->is(self::AUTORISEES)) {
            return response()->json([
                'message' => 'Vous devez changer votre mot de passe temporaire avant de continuer.',
                'code' => 'mot_de_passe_a_changer',
            ], 403);
        }

        return $next($request);
    }
}
