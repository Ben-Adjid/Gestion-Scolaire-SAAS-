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
            // Formules qui incluent le module, dans l'ordre du catalogue : « VIP ou Entreprise ».
            $formules = collect(config('plans.plans'))
                ->filter(fn ($plan) => in_array($fonctionnalite, $plan['fonctionnalites'], true))
                ->pluck('libelle')
                ->implode(' ou ');

            return response()->json([
                'message' => $formules
                    ? "Cette fonctionnalité nécessite le forfait {$formules}. Contactez l'administrateur pour mettre à niveau votre établissement."
                    : "Cette fonctionnalité n'est pas incluse dans votre formule. Contactez l'administrateur pour mettre à niveau votre établissement.",
                'code' => 'plan_insuffisant',
            ], 403);
        }

        return $next($request);
    }
}
