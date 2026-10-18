<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\Creneau;
use App\Models\EmploiDuTemps;
use App\Models\Enseignant;
use App\Models\Etudiant;
use App\Models\Matiere;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AppelTest extends TestCase
{
    use RefreshDatabase;

    private function makeEnseignantUser(int $classeId, array $matiereIds): User
    {
        $enseignant = Enseignant::factory()->create();
        $user = User::factory()->create(['role' => 'enseignant', 'enseignant_id' => $enseignant->id]);

        $creneau = Creneau::firstOrCreate(
            ['ordre' => 1],
            ['heure_debut' => '08:00', 'heure_fin' => '09:00', 'ordre' => 1]
        );

        $enseignant->classes()->syncWithoutDetaching([$classeId]);
        $enseignant->matieres()->syncWithoutDetaching($matiereIds);

        foreach ($matiereIds as $matiereId) {
            EmploiDuTemps::create([
                'classe_id' => $classeId,
                'matiere_id' => $matiereId,
                'enseignant_id' => $enseignant->id,
                'jour' => 1,
                'creneau_id' => $creneau->id,
            ]);
        }

        return $user;
    }

    public function test_enseignant_peut_faire_l_appel_dans_sa_classe(): void
    {
        $classe = Classe::factory()->create();
        $etudiant1 = Etudiant::factory()->create(['classe_id' => $classe->id]);
        $etudiant2 = Etudiant::factory()->create(['classe_id' => $classe->id]);
        $matiere = Matiere::factory()->create();

        $enseignant = $this->makeEnseignantUser($classe->id, [$matiere->id]);

        Sanctum::actingAs($enseignant);

        $this->postJson('/api/appels', [
            'classe_id' => $classe->id,
            'date' => '2025-10-01',
            'presences' => [
                ['etudiant_id' => $etudiant1->id, 'statut' => 'absence'],
                ['etudiant_id' => $etudiant2->id, 'statut' => 'present'],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('absences', [
            'etudiant_id' => $etudiant1->id,
            'date' => '2025-10-01',
            'type' => 'absence',
        ]);
        $this->assertDatabaseMissing('absences', ['etudiant_id' => $etudiant2->id, 'date' => '2025-10-01']);
    }

    public function test_enseignant_ne_peut_pas_faire_l_appel_dans_une_autre_classe(): void
    {
        $classeMine = Classe::factory()->create();
        $classeAutre = Classe::factory()->create();
        $etudiantAutre = Etudiant::factory()->create(['classe_id' => $classeAutre->id]);
        $matiere = Matiere::factory()->create();

        $enseignant = $this->makeEnseignantUser($classeMine->id, [$matiere->id]);

        Sanctum::actingAs($enseignant);

        $this->postJson('/api/appels', [
            'classe_id' => $classeAutre->id,
            'date' => '2025-10-01',
            'presences' => [
                ['etudiant_id' => $etudiantAutre->id, 'statut' => 'absence'],
            ],
        ])->assertStatus(403);
    }

    public function test_enseignant_ne_peut_pas_voir_le_bulletin(): void
    {
        $classe = Classe::factory()->create();
        $etudiant = Etudiant::factory()->create(['classe_id' => $classe->id]);
        $matiere = Matiere::factory()->create();

        $enseignant = $this->makeEnseignantUser($classe->id, [$matiere->id]);

        Sanctum::actingAs($enseignant);

        $this->getJson("/api/etudiants/{$etudiant->id}/bulletin?trimestre=1")
            ->assertStatus(403);
    }

    public function test_appel_valide_est_verrouille(): void
    {
        $classe = Classe::factory()->create();
        $etudiant = Etudiant::factory()->create(['classe_id' => $classe->id]);
        $matiere = Matiere::factory()->create();

        $enseignant = $this->makeEnseignantUser($classe->id, [$matiere->id]);

        Sanctum::actingAs($enseignant);

        $payload = [
            'classe_id' => $classe->id,
            'date' => '2025-10-01',
            'valider' => true,
            'presences' => [
                ['etudiant_id' => $etudiant->id, 'statut' => 'absence'],
            ],
        ];

        $this->postJson('/api/appels', $payload)->assertOk()
            ->assertJsonPath('valide', true);

        // Re-sauvegarde après validation -> refus
        $this->postJson('/api/appels', [
            'classe_id' => $classe->id,
            'date' => '2025-10-01',
            'presences' => [
                ['etudiant_id' => $etudiant->id, 'statut' => 'present'],
            ],
        ])->assertStatus(422);
    }

    public function test_censeur_peut_confirmer_un_appel_valide(): void
    {
        $classe = Classe::factory()->create();
        $etudiant = Etudiant::factory()->create(['classe_id' => $classe->id]);
        $matiere = Matiere::factory()->create();

        $enseignant = $this->makeEnseignantUser($classe->id, [$matiere->id]);
        $censeur = User::factory()->create(['role' => 'censeur']);

        Sanctum::actingAs($enseignant);

        $this->postJson('/api/appels', [
            'classe_id' => $classe->id,
            'date' => '2025-10-01',
            'valider' => true,
            'presences' => [
                ['etudiant_id' => $etudiant->id, 'statut' => 'absence'],
            ],
        ])->assertOk();

        $appel = \App\Models\Appel::first();

        // Un enseignant ne peut pas confirmer
        $this->postJson("/api/appels/{$appel->id}/confirmer")->assertStatus(403);

        // Le censeur confirme
        Sanctum::actingAs($censeur);

        $this->postJson("/api/appels/{$appel->id}/confirmer")->assertOk();

        $this->assertDatabaseHas('appels', [
            'id' => $appel->id,
            'statut' => 'confirme',
            'confirme_par' => $censeur->id,
        ]);
    }

    public function test_en_attente_liste_les_appels_a_confirmer(): void
    {
        $classe = Classe::factory()->create();
        $etudiant = Etudiant::factory()->create(['classe_id' => $classe->id]);
        $matiere = Matiere::factory()->create();

        $enseignant = $this->makeEnseignantUser($classe->id, [$matiere->id]);
        $censeur = User::factory()->create(['role' => 'censeur']);

        Sanctum::actingAs($enseignant);

        $this->postJson('/api/appels', [
            'classe_id' => $classe->id,
            'date' => '2025-10-01',
            'valider' => true,
            'presences' => [
                ['etudiant_id' => $etudiant->id, 'statut' => 'absence'],
            ],
        ])->assertOk();

        Sanctum::actingAs($censeur);

        $this->getJson('/api/appels/en-attente')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.nb_absents', 1);
    }
}
