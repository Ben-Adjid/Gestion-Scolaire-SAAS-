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
        ?string $description = null
    ): void {
        try {
            Audit::create([
                'user_id' => $user?->id,
                'action' => $action,
                'module' => $module,
                'entite_id' => $entiteId,
                'description' => $description,
                'ip' => request()->ip(),
            ]);
        } catch (\Throwable $e) {
            // Le journal ne doit jamais casser le flux principal.
        }
    }
}
