<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\Etudiant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DossierInscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_secretaire_peut_enregistrer_le_dossier_d_inscription(): void
    {
        $secretaire = User::factory()->create(['role' => 'secretaire']);
        $etudiant = Etudiant::factory()->create();

        Sanctum::actingAs($secretaire);

        $this->postJson("/api/etudiants/{$etudiant->id}/dossier", [
            'acte_naissance' => true,
            'bulletin' => true,
            'photo' => false,
        ])->assertOk()
            ->assertJsonPath('dossier.complet', false);

        $this->assertDatabaseHas('dossiers_inscription', [
            'etudiant_id' => $etudiant->id,
            'acte_naissance' => true,
            'bulletin' => true,
            'photo' => false,
        ]);
    }

    public function test_lookup_retourne_le_dossier_existant(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $etudiant = Etudiant::factory()->create(['matricule' => 'GS-99999']);

        Sanctum::actingAs($admin);

        $this->postJson("/api/etudiants/{$etudiant->id}/dossier", [
            'acte_naissance' => true,
            'bulletin' => false,
            'photo' => true,
        ])->assertOk();

        $this->getJson('/api/etudiants/lookup?matricule=GS-99999')
            ->assertOk()
            ->assertJsonPath('dossier.acte_naissance', true)
            ->assertJsonPath('dossier.photo', true);
    }

    public function test_enseignant_ne_peut_pas_modifier_le_dossier(): void
    {
        $enseignant = User::factory()->create(['role' => 'enseignant']);
        $classe = Classe::factory()->create();
        $etudiant = Etudiant::factory()->create(['classe_id' => $classe->id]);

        Sanctum::actingAs($enseignant);

        $this->postJson("/api/etudiants/{$etudiant->id}/dossier", [
            'photo' => true,
        ])->assertStatus(403);
    }
}
