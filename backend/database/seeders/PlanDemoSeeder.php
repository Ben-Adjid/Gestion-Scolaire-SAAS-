<?php

namespace Database\Seeders;

use App\Models\Absence;
use App\Models\AbsenceJustification;
use App\Models\Classe;
use App\Models\Etablissement;
use App\Models\Etudiant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Données de démonstration par formule (Standard, VIP, Entreprise) :
 * un administrateur par établissement, et pour la formule VIP un élève, son parent
 * et une demande de justification en attente pour le CPE.
 * Mot de passe commun : password (même convention que les autres comptes de démo).
 */
class PlanDemoSeeder extends Seeder
{
    public function run(): void
    {
        foreach (array_keys(config('plans.plans')) as $plan) {
            $etablissement = Etablissement::firstOrCreate(
                ['nom' => "Lycée démo {$plan}"],
                ['plan' => $plan, 'statut_abonnement' => 'actif', 'annee_scolaire' => date('Y') . '-' . (date('Y') + 1)]
            );

            User::withoutGlobalScopes()->updateOrCreate(
                ['email' => "admin.{$plan}@test.com"],
                [
                    'name' => 'Admin ' . config("plans.plans.{$plan}.libelle"),
                    'password' => Hash::make('password'),
                    'role' => 'admin',
                    'etablissement_id' => $etablissement->id,
                    'email_verified_at' => now(),
                ]
            );

            if ($plan === 'vip') {
                $this->creerFamilleDemo($etablissement);
            }
        }
    }

    private function creerFamilleDemo(Etablissement $etablissement): void
    {
        $classe = Classe::withoutGlobalScopes()->firstOrCreate(
            ['etablissement_id' => $etablissement->id, 'nom' => 'Terminale A'],
            ['niveau' => 'Terminale']
        );

        $eleve = Etudiant::withoutGlobalScopes()->firstOrCreate(
            ['etablissement_id' => $etablissement->id, 'nom' => 'Diop', 'prenom' => 'Awa'],
            ['classe_id' => $classe->id, 'genre' => 'F', 'date_naissance' => '2008-05-12']
        );

        $parent = User::withoutGlobalScopes()->updateOrCreate(
            ['email' => 'parent.vip@test.com'],
            [
                'name' => 'Parent démo VIP',
                'password' => Hash::make('password'),
                'role' => 'parent',
                'etablissement_id' => $etablissement->id,
                'email_verified_at' => now(),
            ]
        );
        $parent->enfants()->syncWithoutDetaching([$eleve->id => ['lien' => 'pere']]);

        $absence = Absence::firstOrCreate(
            ['etudiant_id' => $eleve->id, 'date' => '2026-10-15'],
            ['type' => 'absence', 'justifie' => false]
        );

        AbsenceJustification::withoutGlobalScopes()->firstOrCreate(
            ['absence_id' => $absence->id],
            [
                'demandeur_id' => $parent->id,
                'motif' => 'Rendez-vous médical',
                'statut' => 'en_attente',
                'etablissement_id' => $etablissement->id,
            ]
        );
    }
}
