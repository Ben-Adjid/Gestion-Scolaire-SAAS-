<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureActiveSubscription
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user || $user->isSuperAdmin() || $request->is('api/logout')) {
            return $next($request);
        }

        if (! $user->etablissement || ! $user->etablissement->isActif()) {
            return response()->json([
                'message' => "L'abonnement de votre établissement n'est pas actif.",
                'code' => 'subscription_inactive',
            ], 403);
        }

        return $next($request);
    }
}
