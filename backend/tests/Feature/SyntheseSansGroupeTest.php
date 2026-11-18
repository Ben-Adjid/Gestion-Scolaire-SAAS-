<?php

namespace Tests\Feature;

use App\Models\Etablissement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Sans groupe scolaire, la synthèse répond une réponse claire (et non une erreur). */
class SyntheseSansGroupeTest extends TestCase
{
    use RefreshDatabase;

    public function test_etablissement_entreprise_sans_groupe_obtient_une_reponse_vide(): void
    {
        $etab = Etablissement::create(['nom' => 'Seul', 'plan' => 'entreprise', 'statut_abonnement' => 'actif']);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'etablissement_id' => $etab->id]));

        $this->getJson('/api/groupe/synthese')
            ->assertOk()
            ->assertJsonPath('groupe', null)
            ->assertJsonStructure(['message', 'sites', 'totaux']);
    }
}
