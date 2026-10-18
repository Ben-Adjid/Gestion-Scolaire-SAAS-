<?php

namespace Database\Seeders;

use App\Models\Classe;
use App\Models\Absence;
use App\Models\Creneau;
use App\Models\EmploiDuTemps;
use App\Models\Enseignant;
use App\Models\Etablissement;
use App\Models\Etudiant;
use App\Models\Inscription;
use App\Models\Matiere;
use App\Models\Note;
use App\Models\Regle;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    private const CLASSES = [
        ['nom' => '6ème A', 'niveau' => '6ème'],
        ['nom' => '6ème B', 'niveau' => '6ème'],
        ['nom' => '5ème A', 'niveau' => '5ème'],
        ['nom' => '5ème B', 'niveau' => '5ème'],
        ['nom' => '4ème A', 'niveau' => '4ème'],
        ['nom' => '3ème A', 'niveau' => '3ème'],
        ['nom' => 'Seconde A', 'niveau' => 'Seconde'],
        ['nom' => 'Seconde B', 'niveau' => 'Seconde'],
        ['nom' => 'Première A', 'niveau' => 'Première'],
        ['nom' => 'Terminale A', 'niveau' => 'Terminale'],
    ];

    private const MATIERES = [
        ['code' => 'MATH', 'nom' => 'Mathématiques', 'coefficient' => 4, 'couleur' => '#4f46e5'],
        ['code' => 'FR', 'nom' => 'Français', 'coefficient' => 4, 'couleur' => '#db2777'],
        ['code' => 'EN', 'nom' => 'Anglais', 'coefficient' => 3, 'couleur' => '#2563eb'],
        ['code' => 'HG', 'nom' => 'Histoire-Géographie', 'coefficient' => 3, 'couleur' => '#d97706'],
        ['code' => 'PC', 'nom' => 'Physique-Chimie', 'coefficient' => 3, 'couleur' => '#0891b2'],
        ['code' => 'SVT', 'nom' => 'SVT', 'coefficient' => 3, 'couleur' => '#16a34a'],
        ['code' => 'ESP', 'nom' => 'Espagnol', 'coefficient' => 2, 'couleur' => '#e11d48'],
        ['code' => 'EPS', 'nom' => 'EPS', 'coefficient' => 1, 'couleur' => '#7c3aed'],
        ['code' => 'PHIL', 'nom' => 'Philosophie', 'coefficient' => 4, 'couleur' => '#0f766e'],
    ];

    public function run()
    {
        Etablissement::updateOrCreate(
            ['nom' => 'Lycée Ibnou Cina'],
            [
                'devise' => 'Unité — Travail — Progrès',
                'ministere' => "Ministère de l'Éducation Nationale",
                'annee_scolaire' => '2025-2026',
                'prefixe_matricule' => 'LIC',
                'logo_path' => 'logo.jpg',
            ]
        );

        // Create all 10 users with correct roles
        User::updateOrCreate(
            ['email' => 'superadmin@test.com'],
            [
                'name' => 'Super Administrateur',
                'password' => Hash::make('password'),
                'role' => 'super_admin',
                'etablissement_id' => null,
            ]
        );

        User::updateOrCreate(
            ['email' => 'admin@test.com'],
            ['name' => 'Administrateur', 'password' => Hash::make('password'), 'role' => 'admin']
        );

        User::updateOrCreate(
            ['email' => 'proviseur@test.com'],
            ['name' => 'Proviseur', 'password' => Hash::make('password'), 'role' => 'proviseur']
        );

        User::updateOrCreate(
            ['email' => 'censeur@test.com'],
            ['name' => 'Censeur Pédagogique', 'password' => Hash::make('password'), 'role' => 'censeur']
        );

        User::updateOrCreate(
            ['email' => 'cpe@test.com'],
            ['name' => 'Conseiller Pédagogique', 'password' => Hash::make('password'), 'role' => 'cpe']
        );

        User::updateOrCreate(
            ['email' => 'secretaire@test.com'],
            ['name' => 'Secrétaire', 'password' => Hash::make('password'), 'role' => 'secretaire']
        );

        User::updateOrCreate(
            ['email' => 'gestionnaire@test.com'],
            ['name' => 'Gestionnaire', 'password' => Hash::make('password'), 'role' => 'gestionnaire']
        );

        User::updateOrCreate(
            ['email' => 'agent_comptable@test.com'],
            ['name' => 'Agent Comptable', 'password' => Hash::make('password'), 'role' => 'agent_comptable']
        );

        User::updateOrCreate(
            ['email' => 'responsable_it@test.com'],
            ['name' => 'Responsable IT', 'password' => Hash::make('password'), 'role' => 'responsable_it']
        );

        foreach (self::CLASSES as $classe) {
            Classe::updateOrCreate(['nom' => $classe['nom']], $classe);
        }

        foreach (self::MATIERES as $matiere) {
            Matiere::updateOrCreate(['nom' => $matiere['nom']], $matiere);
        }

        $enseignants = Enseignant::factory(12)->create();

        // Enseignant de démonstration (compte enseignant@test.com)
        $profDemo = Enseignant::updateOrCreate(
            ['email' => 'awa.diop@ecole.com'],
            [
                'nom' => 'Diop',
                'prenom' => 'Awa',
                'specialite' => 'Mathématiques',
            ]
        );

        if (! Regle::exists()) {
            Regle::create([]);
        }

        User::updateOrCreate(
            ['email' => 'enseignant@test.com'],
            ['name' => 'Awa Diop', 'password' => Hash::make('password'), 'role' => 'enseignant', 'enseignant_id' => $profDemo->id]
        );

        $classes = Classe::all();
        $matieres = Matiere::all();
        $annee = '2025-2026';

        foreach (range(1, 72) as $i) {
            $classe = $classes[$i % $classes->count()];
            $base = $this->randomLevel();

            $etudiant = Etudiant::factory()->create([
                'classe_id' => $classe->id,
                'annee_scolaire' => $annee,
            ]);

            Inscription::create([
                'etudiant_id' => $etudiant->id,
                'classe_id' => $classe->id,
                'annee_scolaire' => $annee,
                'date_inscription' => now()->subMonths(rand(1, 8))->format('Y-m-d'),
                'statut' => 'active',
                'type' => 'nouveau',
            ]);

            foreach ($matieres as $matiere) {
                foreach ([1, 2, 3] as $trimestre) {
                    foreach (range(1, rand(2, 3)) as $_) {
                        Note::create([
                            'etudiant_id' => $etudiant->id,
                            'matiere_id' => $matiere->id,
                            'trimestre' => $trimestre,
                            'type' => ['devoir', 'composition', 'examen'][array_rand(['devoir', 'composition', 'examen'])],
                            'valeur' => $this->clamp($base + random_int(-30, 30) / 10, 4, 20),
                            'statut' => $trimestre <= 2 ? 'valide' : 'brouillon',
                        ]);
                    }
                }
            }
        }

        $this->seedEmploiDuTemps($classes, $matieres, $enseignants);
        $this->seedEmploiDuTempsProf($matieres, $profDemo);
        $this->seedAbsences();
    }

    /**
     * Emploi du temps restreint de l'enseignant de démonstration :
     * Mathématiques + Physique-Chimie dans 2 classes uniquement.
     */
    private function seedEmploiDuTempsProf($matieres, $prof): void
    {
        $math = $matieres->firstWhere('nom', 'Mathématiques');
        $pc = $matieres->firstWhere('nom', 'Physique-Chimie');
        $classes = Classe::whereIn('nom', ['6ème A', '5ème A'])->get();

        foreach ($classes as $classe) {
            foreach (range(1, 5) as $jour) {
                EmploiDuTemps::create([
                    'classe_id' => $classe->id,
                    'matiere_id' => $math->id,
                    'enseignant_id' => $prof->id,
                    'jour' => $jour,
                    'creneau_id' => random_int(1, 4),
                    'salle' => 'S'.random_int(1, 20),
                ]);
            }

            EmploiDuTemps::create([
                'classe_id' => $classe->id,
                'matiere_id' => $pc->id,
                'enseignant_id' => $prof->id,
                'jour' => 3,
                'creneau_id' => 5,
                'salle' => 'S'.random_int(1, 20),
            ]);
        }
    }

    private function seedAbsences(): void
    {
        $etudiants = Etudiant::all();

        foreach ($etudiants as $etudiant) {
            $nb = random_int(0, 3);

            for ($i = 0; $i < $nb; $i++) {
                Absence::create([
                    'etudiant_id' => $etudiant->id,
                    'date' => now()->subDays(random_int(1, 60))->format('Y-m-d'),
                    'type' => random_int(1, 100) <= 70 ? 'absence' : 'retard',
                    'justifie' => random_int(1, 100) <= 50,
                    'motif' => random_int(1, 100) <= 50 ? ['Maladie', 'Rendez-vous médical', 'Problème familial'][array_rand(['Maladie', 'Rendez-vous médical', 'Problème familial'])] : null,
                ]);
            }
        }
    }

    private function seedEmploiDuTemps($classes, $matieres, $enseignants): void
    {
        $creneaux = [
            ['heure_debut' => '08:00', 'heure_fin' => '09:00', 'ordre' => 1],
            ['heure_debut' => '09:00', 'heure_fin' => '10:00', 'ordre' => 2],
            ['heure_debut' => '10:00', 'heure_fin' => '11:00', 'ordre' => 3],
            ['heure_debut' => '11:00', 'heure_fin' => '12:00', 'ordre' => 4],
            ['heure_debut' => '14:00', 'heure_fin' => '15:00', 'ordre' => 5],
            ['heure_debut' => '15:00', 'heure_fin' => '16:00', 'ordre' => 6],
            ['heure_debut' => '16:00', 'heure_fin' => '17:00', 'ordre' => 7],
            ['heure_debut' => '17:00', 'heure_fin' => '18:00', 'ordre' => 8],
        ];

        foreach ($creneaux as $creneau) {
            Creneau::updateOrCreate(['ordre' => $creneau['ordre']], $creneau);
        }

        foreach ($classes as $classe) {
            foreach (range(1, 5) as $jour) {
                foreach (range(1, 8) as $creneauId) {
                    if (random_int(1, 100) > 70) {
                        continue;
                    }
                    EmploiDuTemps::create([
                        'classe_id' => $classe->id,
                        'matiere_id' => $matieres->random()->id,
                        'enseignant_id' => $enseignants->random()->id,
                        'jour' => $jour,
                        'creneau_id' => $creneauId,
                        'salle' => 'S'.random_int(1, 20),
                    ]);
                }
            }
        }
    }

    private function randomLevel(): float
    {
        // Distribution centrée sur 13 avec quelques très bons élèves
        $pool = [8, 9, 10, 11, 12, 12, 13, 13, 14, 14, 15, 15, 16, 17, 18];

        return (float) $pool[array_rand($pool)];
    }

    private function clamp(float $value, float $min, float $max): float
    {
        return round(max($min, min($max, $value)), 2);
    }
}
