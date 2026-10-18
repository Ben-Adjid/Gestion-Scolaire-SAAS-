<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\Etudiant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InscriptionRolesTest extends TestCase
{
    use RefreshDatabase;

    private function payload(int $etudiantId, int $classeId): array
    {
        return [
            'etudiant_id' => $etudiantId,
            'classe_id' => $classeId,
            'annee_scolaire' => '2025-2026',
            'date_inscription' => '2025-09-01',
            'statut' => 'active',
            'type' => 'ancien',
        ];
    }

    public function test_secretaire_peut_inscrire_et_censeur_non(): void
    {
        $secretaire = User::factory()->create(['role' => 'secretaire']);
        $censeur = User::factory()->create(['role' => 'censeur']);
        $etudiant = Etudiant::factory()->create();
        $classe = Classe::factory()->create();

        Sanctum::actingAs($secretaire);
        $this->postJson('/api/inscriptions', $this->payload($etudiant->id, $classe->id))
            ->assertCreated();

        Sanctum::actingAs($censeur);
        $this->postJson('/api/inscriptions', $this->payload($etudiant->id, $classe->id))
            ->assertStatus(403);
    }

    public function test_lookup_par_matricule_retourne_moyenne_et_decision(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $etudiant = Etudiant::factory()->create(['matricule' => 'GS-00001']);

        Sanctum::actingAs($admin);

        $this->getJson('/api/etudiants/lookup?matricule=GS-00001')
            ->assertOk()
            ->assertJsonStructure(['etudiant', 'moyenne_annuelle', 'decision']);
    }

    public function test_nouvel_eleve_cree_etudiant_et_inscription_atomiquement(): void
    {
        $secretaire = User::factory()->create(['role' => 'secretaire']);
        $classe = Classe::factory()->create();

        Sanctum::actingAs($secretaire);

        $this->postJson('/api/inscriptions', [
            'nom' => 'Diallo',
            'prenom' => 'Fatou',
            'genre' => 'F',
            'classe_id' => $classe->id,
            'annee_scolaire' => '2025-2026',
            'date_inscription' => '2025-09-01',
            'statut' => 'active',
            'type' => 'nouveau',
        ])->assertCreated()
            ->assertJsonPath('inscription.etudiant.nom', 'Diallo');

        $this->assertDatabaseHas('etudiants', [
            'nom' => 'Diallo',
            'prenom' => 'Fatou',
            'classe_id' => $classe->id,
            'annee_scolaire' => '2025-2026',
        ]);

        $this->assertDatabaseHas('inscriptions', [
            'classe_id' => $classe->id,
            'type' => 'nouveau',
            'annee_scolaire' => '2025-2026',
        ]);
    }
}
