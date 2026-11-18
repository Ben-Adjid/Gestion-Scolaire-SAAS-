<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Un parent ne peut appeler que son espace et quelques routes personnelles.
 */
class ReserveAuxParents
{
    private const AUTORISEES = ['api/me', 'api/logout', 'api/etablissement', 'api/parent/*', 'api/justifications', 'api/messages', 'api/messages/*', 'api/cahier-texte', 'api/actualites', 'api/calendrier', 'api/2fa/*', 'api/mot-de-passe/*'];

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && $user->role === 'parent' && ! $request->is(self::AUTORISEES)) {
            return response()->json(['message' => "Cet espace n'est pas accessible aux parents."], 403);
        }

        return $next($request);
    }
}
