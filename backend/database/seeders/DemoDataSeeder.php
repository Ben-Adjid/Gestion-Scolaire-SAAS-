<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Classe;
use App\Models\Etudiant;
use App\Models\Enseignant;
use App\Models\Matiere;
use App\Models\Regle;
use App\Models\Etablissement;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    public function run()
    {
        $etablissement = Etablissement::firstOrCreate(
            ['id' => 1],
            ['nom' => 'Lycée Central']
        );

        Regle::firstOrCreate(
            ['etablissement_id' => $etablissement->id],
            [
                'seuil_admission' => 10,
                'seuil_bien' => 12,
                'seuil_tres_bien' => 14,
                'seuil_excellence' => 16,
                'nb_tableau_honneur' => 5,
            ]
        );

        $adminUser = User::firstOrCreate(
            ['email' => 'admin@test.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'etablissement_id' => $etablissement->id,
                'email_verified_at' => now(),
            ]
        );

        $proviseurUser = User::firstOrCreate(
            ['email' => 'proviseur@test.com'],
            [
                'name' => 'Proviseur User',
                'password' => Hash::make('password'),
                'role' => 'proviseur',
                'etablissement_id' => $etablissement->id,
                'email_verified_at' => now(),
            ]
        );

        $enseignantUser = User::firstOrCreate(
            ['email' => 'enseignant@test.com'],
            [
                'name' => 'Enseignant User',
                'password' => Hash::make('password'),
                'role' => 'enseignant',
                'etablissement_id' => $etablissement->id,
                'email_verified_at' => now(),
            ]
        );

        $superAdminUser = User::firstOrCreate(
            ['email' => 'superadmin@test.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'role' => 'super_admin',
                'etablissement_id' => $etablissement->id,
                'email_verified_at' => now(),
            ]
        );

        $gestionnaireUser = User::firstOrCreate(
            ['email' => 'gestionnaire@test.com'],
            [
                'name' => 'Gestionnaire',
                'password' => Hash::make('password'),
                'role' => 'gestionnaire',
                'etablissement_id' => $etablissement->id,
                'email_verified_at' => now(),
            ]
        );

        $censeurUser = User::firstOrCreate(
            ['email' => 'censeur@test.com'],
            [
                'name' => 'Censeur',
                'password' => Hash::make('password'),
                'role' => 'censeur',
                'etablissement_id' => $etablissement->id,
                'email_verified_at' => now(),
            ]
        );

        $cpeUser = User::firstOrCreate(
            ['email' => 'cpe@test.com'],
            [
                'name' => 'CPE',
                'password' => Hash::make('password'),
                'role' => 'cpe',
                'etablissement_id' => $etablissement->id,
                'email_verified_at' => now(),
            ]
        );

        $secretaireUser = User::firstOrCreate(
            ['email' => 'secretaire@test.com'],
            [
                'name' => 'Secrétaire',
                'password' => Hash::make('password'),
                'role' => 'secretaire',
                'etablissement_id' => $etablissement->id,
                'email_verified_at' => now(),
            ]
        );

        $agentComptableUser = User::firstOrCreate(
            ['email' => 'agent_comptable@test.com'],
            [
                'name' => 'Agent Comptable',
                'password' => Hash::make('password'),
                'role' => 'agent_comptable',
                'etablissement_id' => $etablissement->id,
                'email_verified_at' => now(),
            ]
        );

        $responsableItUser = User::firstOrCreate(
            ['email' => 'responsable_it@test.com'],
            [
                'name' => 'Responsable IT',
                'password' => Hash::make('password'),
                'role' => 'responsable_it',
                'etablissement_id' => $etablissement->id,
                'email_verified_at' => now(),
            ]
        );

        $matieres = [
            ['nom' => 'Mathématiques', 'code' => 'MATH', 'coefficient' => 3, 'couleur' => '#FF6B6B'],
            ['nom' => 'Français', 'code' => 'FR', 'coefficient' => 2, 'couleur' => '#4ECDC4'],
            ['nom' => 'Anglais', 'code' => 'EN', 'coefficient' => 2, 'couleur' => '#45B7D1'],
            ['nom' => 'Sciences', 'code' => 'SCI', 'coefficient' => 2, 'couleur' => '#FFA07A'],
        ];

        foreach ($matieres as $matiere) {
            Matiere::firstOrCreate(['code' => $matiere['code']], array_merge($matiere, ['etablissement_id' => $etablissement->id]));
        }

        $classes = [];
        foreach (['6ème', '5ème', '4ème', '3ème', 'Seconde', 'Première', 'Terminale'] as $niveau) {
            for ($i = 1; $i <= 3; $i++) {
                $classes[] = Classe::firstOrCreate(
                    ['nom' => "{$niveau} ".chr(64 + $i), 'etablissement_id' => $etablissement->id],
                    [
                        'niveau' => $niveau,
                        'effectif_max' => 35,
                        'cycle' => in_array($niveau, ['6ème', '5ème', '4ème', '3ème']) ? 'collège' : 'lycée',
                        'etablissement_id' => $etablissement->id,
                    ]
                );
            }
        }

        $enseignants = [];
        for ($i = 1; $i <= 10; $i++) {
            $enseignants[] = Enseignant::firstOrCreate(
                ['email' => "prof{$i}@test.com"],
                [
                    'nom' => "Professeur {$i}",
                    'prenom' => "Prof",
                    'telephone' => "06000000{$i}0",
                    'specialite' => $matieres[($i - 1) % count($matieres)]['nom'],
                    'etablissement_id' => $etablissement->id,
                ]
            );
        }

        foreach ($classes as $classe) {
            for ($i = 1; $i <= 30; $i++) {
                Etudiant::factory()->create([
                    'classe_id' => $classe->id,
                    'etablissement_id' => $etablissement->id,
                ]);
            }
        }

        $this->command->info('Demo data seeded successfully!');
    }
}
