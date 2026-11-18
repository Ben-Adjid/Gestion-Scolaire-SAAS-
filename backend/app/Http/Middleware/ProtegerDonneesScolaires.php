<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * La plateforme SaaS n'accède pas aux données scolaires des établissements
 * (élèves, notes, dossiers, paiements des familles). Le super admin n'a accès
 * qu'à la gestion des écoles, des groupes, des abonnements et du support.
 */
class ProtegerDonneesScolaires
{
    private const AUTORISEES = [
        'api/me',
        'api/logout',
        'api/etablissement',
        'api/2fa/*',
        'api/super-admin/*',
        'api/mot-de-passe/*',
    ];

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && $user->role === 'super_admin' && ! $request->is(self::AUTORISEES)) {
            return response()->json([
                'message' => "La plateforme n'accède pas aux données scolaires des établissements. Utilisez la vue plateforme.",
                'code' => 'donnees_scolaires_reservees',
            ], 403);
        }

        return $next($request);
    }
}
