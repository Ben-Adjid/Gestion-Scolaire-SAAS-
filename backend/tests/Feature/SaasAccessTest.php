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

    public function test_superadmin_gere_la_plateforme_sans_lire_les_donnees_des_ecoles(): void
    {
        $superadmin = User::factory()->create([
            'role' => 'super_admin',
            'etablissement_id' => null,
        ]);
        Sanctum::actingAs($superadmin);

        // Gestion SaaS : autorisée.
        $this->getJson('/api/super-admin/dashboard')->assertOk();
        $this->getJson('/api/super-admin/etablissements')->assertOk();
        $this->getJson('/api/etablissement')->assertOk()->assertJsonPath('nom', 'Plateforme de gestion scolaire');

        // Données scolaires d'un établissement : refusées, quel que soit le module.
        $this->getJson('/api/classes')
            ->assertForbidden()
            ->assertJsonPath('code', 'donnees_scolaires_reservees');
        $this->getJson('/api/etudiants')->assertForbidden();
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

    public function test_un_abonnement_suspendu_peut_se_connecter_pour_demander_un_renouvellement(): void
    {
        $etablissement = Etablissement::first();
        $etablissement->update(['statut_abonnement' => 'suspendu']);
        User::factory()->create([
            'email' => 'suspendu@test.com',
            'role' => 'admin',
            'etablissement_id' => $etablissement->id,
        ]);

        $connexion = $this->postJson('/api/login', [
            'email' => 'suspendu@test.com',
            'password' => 'password',
        ])->assertOk()->assertJsonStructure(['token', 'user']);

        $this->withToken($connexion->json('token'))->getJson('/api/me')->assertOk();
        $this->withToken($connexion->json('token'))->getJson('/api/classes')
            ->assertForbidden()
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
