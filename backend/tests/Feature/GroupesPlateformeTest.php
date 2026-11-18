<?php

namespace Tests\Feature;

use App\Models\Etablissement;
use App\Models\GroupeScolaire;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Gestion des groupes scolaires par la plateforme. */
class GroupesPlateformeTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_plateforme_cree_un_groupe_et_y_rattache_un_site(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'super_admin', 'etablissement_id' => null]));
        $site = Etablissement::create(['nom' => 'Site Nord', 'plan' => 'entreprise', 'statut_abonnement' => 'actif']);

        $groupe = $this->postJson('/api/super-admin/groupes', ['nom' => 'Groupe Al-Nour'])->assertCreated()->json('groupe');

        $this->getJson('/api/super-admin/groupes')
            ->assertOk()
            ->assertJsonPath('groupes.0.nom', 'Groupe Al-Nour')
            ->assertJsonPath('groupes.0.sites', [])
            ->assertJsonFragment(['nom' => 'Site Nord']);

        $this->postJson("/api/super-admin/etablissements/{$site->id}/groupe", ['groupe_scolaire_id' => $groupe['id']])->assertOk();
        $this->assertSame($groupe['id'], $site->fresh()->groupe_scolaire_id);
        $this->getJson('/api/super-admin/groupes')->assertJsonPath('groupes.0.sites.0.nom', 'Site Nord');

        $this->postJson("/api/super-admin/etablissements/{$site->id}/groupe", ['groupe_scolaire_id' => null])->assertOk();
        $this->assertNull($site->fresh()->groupe_scolaire_id);
        $this->assertSame(1, GroupeScolaire::count());
    }

    public function test_un_admin_d_etablissement_ne_gere_pas_les_groupes(): void
    {
        $etab = Etablissement::first();
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'etablissement_id' => $etab->id]));

        $this->getJson('/api/super-admin/groupes')->assertForbidden();
    }
}
