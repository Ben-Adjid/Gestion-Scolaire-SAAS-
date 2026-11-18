<?php

namespace Tests\Feature;

use App\Models\Etablissement;
use App\Models\GroupeScolaire;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Groupes : renommer, supprimer (seulement vide). Liste des écoles : score de santé inclus. */
class GroupesEtRisqueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(['role' => 'super_admin', 'etablissement_id' => null]));
    }

    public function test_un_groupe_se_renomme(): void
    {
        $groupe = GroupeScolaire::create(['nom' => 'Ancien']);

        $this->putJson("/api/super-admin/groupes/{$groupe->id}", ['nom' => 'Nouveau nom'])->assertOk();

        $this->assertSame('Nouveau nom', $groupe->fresh()->nom);
    }

    public function test_un_groupe_avec_des_sites_ne_se_supprime_pas(): void
    {
        $groupe = GroupeScolaire::create(['nom' => 'Plein']);
        Etablissement::create(['nom' => 'Site', 'plan' => 'entreprise', 'statut_abonnement' => 'actif', 'groupe_scolaire_id' => $groupe->id]);

        $this->deleteJson("/api/super-admin/groupes/{$groupe->id}")->assertUnprocessable();
        $this->assertNotNull($groupe->fresh());
    }

    public function test_un_groupe_vide_se_supprime(): void
    {
        $groupe = GroupeScolaire::create(['nom' => 'Vide']);

        $this->deleteJson("/api/super-admin/groupes/{$groupe->id}")->assertOk();
        $this->assertNull(GroupeScolaire::find($groupe->id));
    }

    public function test_la_liste_des_ecoles_contient_le_score_de_sante(): void
    {
        Etablissement::create(['nom' => 'Liste', 'plan' => 'standard', 'statut_abonnement' => 'actif']);

        $this->getJson('/api/super-admin/etablissements')
            ->assertOk()
            ->assertJsonFragment(['nom' => 'Liste']);

        $res = $this->getJson('/api/super-admin/etablissements')->json('data');
        $liste = collect($res)->firstWhere('nom', 'Liste');
        $this->assertArrayHasKey('score', $liste['sante']);
    }
}
