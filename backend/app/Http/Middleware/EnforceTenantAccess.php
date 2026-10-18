<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnforceTenantAccess
{
    /**
     * Ensure users can only access resources from their own establishment.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && !$user->isSuperAdmin()) {
            // Store the tenant ID in the request for easy access
            $request->attributes->set('tenant_id', $user->etablissement_id);

            // Verify the establishment is active
            if (!$user->etablissement?->isActif()) {
                return response()->json([
                    'message' => "L'abonnement de votre établissement n'est pas actif.",
                    'code' => 'subscription_inactive',
                ], 403);
            }
        }

        return $next($request);
    }
}
