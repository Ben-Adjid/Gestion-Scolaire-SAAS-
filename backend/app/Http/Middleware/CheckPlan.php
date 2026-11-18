<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckPlan
{
    public function handle(Request $request, Closure $next, string $fonctionnalite)
    {
        $user = $request->user();

        if (! $user || $user->isSuperAdmin()) {
            return $next($request);
        }

        $etablissement = $user->etablissement;
        if (! $etablissement || ! in_array($fonctionnalite, $etablissement->fonctionnalites(), true)) {
            return response()->json([
                'message' => "Cette fonctionnalité n'est pas incluse dans votre abonnement ({$etablissement?->plan_label}). Contactez votre fournisseur pour passer à un plan supérieur.",
                'code' => 'plan_insuffisant',
            ], 403);
        }

        return $next($request);
    }
}
