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

    /** Rôles dont l'accès aux classes suit l'affectation (périmètre). */
    public const ROLES_PERIMETRE = ['proviseur', 'censeur', 'cpe', 'surveillant'];

    /** Libellé comparable : minuscules, sans accents ni espaces superflus (« Lycée » = « lycee »). */
    public static function normaliser(?string $texte): string
    {
        return preg_replace('/\s+/', ' ', trim(\Illuminate\Support\Str::ascii(mb_strtolower((string) $texte))));
    }

    /** Vrai si le compte a un périmètre limité (cycle ou bâtiment), faux s'il couvre tout l'établissement. */
    public static function aUnPerimetre(?User $user): bool
    {
        if (! $user || ! in_array($user->role, self::ROLES_PERIMETRE, true)) {
            return false;
        }
        $perimetre = self::normaliser($user->affectation);

        return ! in_array($perimetre, ['', 'general', 'generale', 'tout', 'tous', 'etablissement'], true);
    }

    /**
     * Classes du périmètre : le cycle (Collège, Lycée) ou le bâtiment (Bâtiment A…) correspondant à l'affectation.
     * Un périmètre qui ne correspond à aucune classe ne donne accès à aucune classe (jamais à toutes).
     */
    public static function perimetreClasseIds(User $user): array
    {
        $perimetre = self::normaliser($user->affectation);

        return \App\Models\Classe::query()
            ->where('etablissement_id', $user->etablissement_id)
            ->get(['id', 'cycle', 'niveau', 'batiment'])
            ->filter(fn ($c) => self::normaliser($c->cycle ?: \App\Models\Classe::cyclePourNiveau($c->niveau)) === $perimetre
                || ($c->batiment && self::normaliser($c->batiment) === $perimetre))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * Classes accessibles (null = pas de restriction) :
     * - enseignant : ses classes ;
     * - proviseur, censeur, CPE, surveillant affectés à un cycle ou un bâtiment : les classes de ce périmètre.
     */
    public static function classeIds(?User $user): ?array
    {
        if (self::aUnPerimetre($user)) {
            return self::perimetreClasseIds($user);
        }

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
