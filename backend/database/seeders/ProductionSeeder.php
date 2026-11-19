<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Données structurelles indispensables en production : uniquement le catalogue des permissions.
 * Les rôles et les niveaux scolaires sont définis dans le code ; aucun compte ni donnée de démonstration.
 * Idempotent : peut être relancé sans créer de doublons.
 */
class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('permissions.modules', []) as $module => $codes) {
            foreach ($codes as $code => $libelle) {
                DB::table('permissions')->updateOrInsert(
                    ['code' => $code],
                    ['module' => $module, 'libelle' => $libelle]
                );
            }
        }
    }
}
