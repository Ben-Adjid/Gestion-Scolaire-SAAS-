<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class TeacherScope
{
    public static function isTeacherRole(?User $user): bool
    {
        return $user !== null && $user->role === 'enseignant';
    }

    /**
     * Matières attribuées à l'enseignant (null = pas de restriction).
     */
    public static function matiereIds(?User $user): ?array
    {
        if (! self::isTeacherRole($user)) {
            return null;
        }

        if (! $user->enseignant_id) {
            return [];
        }

        return DB::table('enseignant_matiere')
            ->where('enseignant_id', $user->enseignant_id)
            ->pluck('matiere_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Classes attribuées à l'enseignant (null = pas de restriction).
     */
    public static function classeIds(?User $user): ?array
    {
        if (! self::isTeacherRole($user)) {
            return null;
        }

        if (! $user->enseignant_id) {
            return [];
        }

        return DB::table('classe_enseignant')
            ->where('enseignant_id', $user->enseignant_id)
            ->pluck('classe_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public static function canAccessMatiere(?User $user, int $matiereId): bool
    {
        $ids = self::matiereIds($user);

        if ($ids === null) {
            return true;
        }

        return in_array($matiereId, $ids, true);
    }

    public static function canAccessClasse(?User $user, int $classeId): bool
    {
        $ids = self::classeIds($user);

        if ($ids === null) {
            return true;
        }

        return in_array($classeId, $ids, true);
    }
}
