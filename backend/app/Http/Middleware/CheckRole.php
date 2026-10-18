<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckRole
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = $request->user();

        if (! $user || ! $user->hasAnyRole($roles)) {
            return response()->json([
                'message' => "Vous n'avez pas les droits nécessaires pour cette action.",
            ], 403);
        }

        return $next($request);
    }
}
