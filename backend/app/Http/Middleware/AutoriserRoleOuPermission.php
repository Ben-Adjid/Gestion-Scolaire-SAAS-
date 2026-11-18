<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Autorise l'accès si le compte possède la permission granulaire demandée,
 * ou si son rôle figure dans la liste.
 *
 * Usage : ->middleware('perm_or_role:notes.modifier,admin,proviseur')
 */
class AutoriserRoleOuPermission
{
    public function handle(Request $request, Closure $next, string $permission, string ...$roles)
    {
        $user = $request->user();

        if (! $user || (! $user->hasPermission($permission) && ! $user->hasAnyRole($roles))) {
            return response()->json([
                'message' => "Vous n'avez pas les droits nécessaires pour cette action.",
                'code' => 'permission_refusee',
            ], 403);
        }

        return $next($request);
    }
}
