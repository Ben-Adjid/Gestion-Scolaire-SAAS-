<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\Etablissement;
use App\Models\Etudiant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DoublonsEtudiantsTest extends TestCase
{
    use RefreshDatabase;

    public function test_secretaire_est_averti_avant_de_creer_un_doublon_et_peut_confirmer(): void
    {
        $etablissement = Etablissement::create([
            'nom' => 'École doublons',
            'plan' => 'standard',
            'statut_abonnement' => 'actif',
        ]);
        $classe = Classe::factory()->create(['etablissement_id' => $etablissement->id]);
        Etudiant::factory()->create([
            'etablissement_id' => $etablissement->id,
            'classe_id' => $classe->id,
            'nom' => 'Diallo',
            'prenom' => 'Aminata',
            'date_naissance' => '2012-04-03',
        ]);

        Sanctum::actingAs(User::factory()->create([
            'role' => 'secretaire',
            'etablissement_id' => $etablissement->id,
        ]));

        $payload = [
            'nom' => 'diallo',
            'prenom' => 'AMINATA',
            'date_naissance' => '2012-04-03',
            'genre' => 'F',
            'classe_id' => $classe->id,
        ];

        $this->postJson('/api/etudiants', $payload)
            ->assertStatus(409)
            ->assertJsonPath('code', 'doublon_potentiel')
            ->assertJsonPath('doublons.0.nom', 'Diallo');

        $this->postJson('/api/etudiants', $payload + ['confirmer_doublon' => true])
            ->assertCreated();

        $this->assertDatabaseCount('etudiants', 2);
    }

    public function test_la_recherche_de_doublons_ne_revele_pas_les_eleves_d_un_autre_etablissement(): void
    {
        $ecoleA = Etablissement::create(['nom' => 'École A', 'plan' => 'standard', 'statut_abonnement' => 'actif']);
        $ecoleB = Etablissement::create(['nom' => 'École B', 'plan' => 'standard', 'statut_abonnement' => 'actif']);
        Etudiant::factory()->create([
            'etablissement_id' => $ecoleB->id,
            'nom' => 'Ndiaye',
            'prenom' => 'Moussa',
            'date_naissance' => '2011-02-01',
        ]);

        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'etablissement_id' => $ecoleA->id]));

        $this->getJson('/api/etudiants/doublons?nom=Ndiaye&prenom=Moussa&date_naissance=2011-02-01')
            ->assertOk()
            ->assertJsonCount(0, 'doublons');
    }
}
