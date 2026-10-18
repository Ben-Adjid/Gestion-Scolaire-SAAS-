<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\Etudiant;
use App\Models\FraisScolaire;
use App\Models\Paiement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FraisScolaireTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_peut_creer_des_frais_et_enregistrer_un_paiement(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $etudiant = Etudiant::factory()->create();

        Sanctum::actingAs($admin);

        $res = $this->postJson('/api/frais-scolaires', [
            'etudiant_id' => $etudiant->id,
            'annee_scolaire' => '2025-2026',
            'montant_total' => 50000,
        ])->assertCreated();

        $fraisId = $res->json('frais.id');

        $this->postJson("/api/frais-scolaires/{$fraisId}/paiements", [
            'montant' => 20000,
            'date_paiement' => '2025-10-01',
            'mode' => 'especes',
        ])->assertCreated()
            ->assertJsonPath('frais.montant_paye', 20000)
            ->assertJsonPath('frais.reste', 30000);
    }

    public function test_enseignant_ne_peut_pas_gerer_les_frais(): void
    {
        $enseignant = User::factory()->create(['role' => 'enseignant']);
        $etudiant = Etudiant::factory()->create();

        Sanctum::actingAs($enseignant);

        $this->postJson('/api/frais-scolaires', [
            'etudiant_id' => $etudiant->id,
            'annee_scolaire' => '2025-2026',
            'montant_total' => 50000,
        ])->assertStatus(403);
    }

    public function test_stats_frais_scolaires(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $etudiant = Etudiant::factory()->create();

        $frais = FraisScolaire::create([
            'etudiant_id' => $etudiant->id,
            'etablissement_id' => $admin->etablissement_id,
            'libelle' => 'Scolarité 2025-2026',
            'annee_scolaire' => '2025-2026',
            'montant' => 50000,
        ]);
        Paiement::create([
            'frais_scolaire_id' => $frais->id,
            'etudiant_id' => $etudiant->id,
            'etablissement_id' => $admin->etablissement_id,
            'montant' => 15000,
            'date_paiement' => '2025-10-01',
            'mode_paiement' => 'especes',
            'statut' => 'completed',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/frais-scolaires/stats')
            ->assertOk()
            ->assertJsonPath('montant_total', 50000)
            ->assertJsonPath('reste', 35000);
    }
}
