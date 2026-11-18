<?php

namespace App\Services;

use App\Models\Audit;
use App\Models\User;

class AuditLogger
{
    public static function log(
        ?User $user,
        string $action,
        string $module,
        ?int $entiteId = null,
        ?string $description = null,
        ?array $avant = null,
        ?array $apres = null
    ): void {
        try {
            Audit::create([
                'user_id' => $user?->id,
                'etablissement_id' => $user?->etablissement_id,
                'action' => $action,
                'module' => $module,
                'entite_id' => $entiteId,
                'description' => $description,
                'ip' => request()->ip(),
                'valeurs_avant' => $avant,
                'valeurs_apres' => $apres,
            ]);
        } catch (\Throwable $e) {
            // Le journal ne doit jamais casser le flux principal.
        }
    }
}
