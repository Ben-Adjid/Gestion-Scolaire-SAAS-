<?php

namespace Tests\Feature;

use App\Models\Etablissement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SaasAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_accede_aux_fonctionnalites_metier_et_saas(): void
    {
        $superadmin = User::factory()->create([
            'role' => 'super_admin',
            'etablissement_id' => null,
        ]);
        Sanctum::actingAs($superadmin);

        $this->getJson('/api/classes')->assertOk();
        $this->getJson('/api/super-admin/dashboard')->assertOk();
    }

    public function test_un_abonnement_suspendu_bloque_l_espace_metier(): void
    {
        $etablissement = Etablissement::first();
        $etablissement->update(['statut_abonnement' => 'suspendu']);
        $admin = User::factory()->create([
            'role' => 'admin',
            'etablissement_id' => $etablissement->id,
        ]);
        Sanctum::actingAs($admin);

        $this->getJson('/api/classes')
            ->assertStatus(403)
            ->assertJsonPath('code', 'subscription_inactive');
    }

    public function test_un_abonnement_suspendu_refuse_aussi_la_connexion(): void
    {
        $etablissement = Etablissement::first();
        $etablissement->update(['statut_abonnement' => 'suspendu']);
        User::factory()->create([
            'email' => 'suspendu@test.com',
            'role' => 'admin',
            'etablissement_id' => $etablissement->id,
        ]);

        $this->postJson('/api/login', [
            'email' => 'suspendu@test.com',
            'password' => 'password',
        ])->assertStatus(403)
            ->assertJsonPath('code', 'subscription_inactive');
    }

    public function test_un_admin_ne_voit_que_les_utilisateurs_de_son_etablissement(): void
    {
        $etablissementA = Etablissement::first();
        $etablissementB = Etablissement::create(['nom' => 'Etablissement B']);
        $adminA = User::factory()->create([
            'role' => 'admin',
            'etablissement_id' => $etablissementA->id,
        ]);
        User::factory()->create([
            'role' => 'admin',
            'etablissement_id' => $etablissementB->id,
        ]);
        Sanctum::actingAs($adminA);

        $this->getJson('/api/users')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
