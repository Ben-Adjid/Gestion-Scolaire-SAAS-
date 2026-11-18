<?php

namespace Tests\Feature;

use App\Models\Etablissement;
use App\Models\SupportDemande;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Fonctionnalités réservées à une formule : contrôles côté serveur (403 si non incluses). */
class FonctionnalitesFormulesTest extends TestCase
{
    use RefreshDatabase;

    private function etablissement(string $plan): Etablissement
    {
        return Etablissement::create(['nom' => "Lycée {$plan}", 'plan' => $plan, 'statut_abonnement' => 'actif']);
    }

    private function admin(Etablissement $etab): User
    {
        return User::factory()->create(['role' => 'admin', 'etablissement_id' => $etab->id]);
    }

    public function test_standard_limite_les_utilisateurs_a_dix(): void
    {
        $etab = $this->etablissement('standard');
        $admin = $this->admin($etab);
        User::factory()->count(9)->create(['role' => 'secretaire', 'etablissement_id' => $etab->id]);
        Sanctum::actingAs($admin);

        $payload = ['name' => 'Nouveau', 'email' => 'nouveau@test.com', 'role' => 'secretaire', 'password' => 'secret123'];

        $this->postJson('/api/users', $payload)
            ->assertForbidden()
            ->assertJsonPath('code', 'limite_utilisateurs');
    }

    public function test_entreprise_n_a_pas_de_limite_d_utilisateurs(): void
    {
        $etab = $this->etablissement('entreprise');
        Sanctum::actingAs($this->admin($etab));

        $this->postJson('/api/users', ['name' => 'Nouveau', 'email' => 'nouveau@test.com', 'role' => 'secretaire', 'password' => 'secret123'])
            ->assertCreated();
    }

    public function test_cles_api_reservees_a_entreprise(): void
    {
        $standard = $this->etablissement('standard');
        Sanctum::actingAs($this->admin($standard));
        $this->getJson('/api/api-tokens')->assertForbidden()->assertJsonPath('code', 'plan_insuffisant');

        $entreprise = $this->etablissement('entreprise');
        Sanctum::actingAs($this->admin($entreprise));
        $cle = $this->postJson('/api/api-tokens', ['name' => 'Intégration'])->assertCreated()->json();
        $this->assertNotEmpty($cle['token']);

        $this->getJson('/api/api-tokens')->assertOk()->assertJsonCount(1);
        $this->deleteJson('/api/api-tokens/'.$cle['cle']['id'])->assertOk();
        $this->getJson('/api/api-tokens')->assertJsonCount(0);
    }

    public function test_personnalisation_reservee_a_entreprise(): void
    {
        $vip = $this->etablissement('vip');
        Sanctum::actingAs($this->admin($vip));
        $this->postJson('/api/etablissement', ['nom' => 'Lycée', 'annee_scolaire' => '2026-2027', 'couleur_principale' => '#123456'])
            ->assertForbidden()
            ->assertJsonPath('code', 'plan_insuffisant');

        $entreprise = $this->etablissement('entreprise');
        Sanctum::actingAs($this->admin($entreprise));
        $this->postJson('/api/etablissement', ['nom' => 'Lycée', 'annee_scolaire' => '2026-2027', 'couleur_principale' => '#123456'])
            ->assertOk();
        $this->assertSame('#123456', $entreprise->fresh()->couleur_principale);
    }

    public function test_support_prioritaire_reserve_a_entreprise(): void
    {
        $vip = $this->etablissement('vip');
        Sanctum::actingAs($this->admin($vip));
        $this->postJson('/api/support', ['sujet' => 'Aide', 'message' => 'Besoin urgent'])->assertForbidden();

        $entreprise = $this->etablissement('entreprise');
        $admin = $this->admin($entreprise);
        Sanctum::actingAs($admin);
        $this->postJson('/api/support', ['sujet' => 'Aide', 'message' => 'Besoin urgent'])->assertCreated();
        $this->getJson('/api/support')->assertOk()->assertJsonCount(1);
        $this->assertSame(1, SupportDemande::count());
    }

    public function test_analyses_avancees_reservees_a_vip_et_plus(): void
    {
        $standard = $this->etablissement('standard');
        Sanctum::actingAs($this->admin($standard));
        $this->getJson('/api/analyses')->assertForbidden();

        $vip = $this->etablissement('vip');
        Sanctum::actingAs($this->admin($vip));
        $this->getJson('/api/analyses')
            ->assertOk()
            ->assertJsonStructure(['indicateurs' => ['eleves', 'moyenne_generale', 'taux_recouvrement', 'absences_total'], 'moyennes_par_classe', 'absences_par_classe', 'absences_par_mois']);
    }

    public function test_renouvellement_par_super_admin_reactive_et_enregistre_le_paiement(): void
    {
        $etab = $this->etablissement('standard');
        $etab->update(['statut_abonnement' => 'expire', 'abonnement_fin' => now()->subDays(3)]);
        Sanctum::actingAs(User::factory()->create(['role' => 'super_admin']));

        $this->postJson("/api/super-admin/etablissements/{$etab->id}/renouveler", [
            'plan' => 'vip', 'duree_mois' => 1, 'montant' => 50000, 'mode_paiement' => 'mobile_money', 'reference' => 'TX-1',
        ])->assertOk();

        $etab->refresh();
        $this->assertSame('actif', $etab->statut_abonnement);
        $this->assertSame('vip', $etab->plan);
        $this->assertTrue($etab->isActif());
        $this->assertDatabaseHas('abonnement_paiements', ['etablissement_id' => $etab->id, 'montant' => 50000]);
    }

    public function test_renouvellement_refuse_a_un_admin(): void
    {
        $etab = $this->etablissement('standard');
        Sanctum::actingAs($this->admin($etab));

        $this->postJson("/api/super-admin/etablissements/{$etab->id}/renouveler", [
            'duree_mois' => 1, 'montant' => 25000, 'mode_paiement' => 'especes',
        ])->assertForbidden();
    }

    public function test_commande_expire_les_abonnements_echus(): void
    {
        $echu = $this->etablissement('standard');
        $echu->update(['abonnement_fin' => now()->subDay()]);
        $valide = $this->etablissement('vip');
        $valide->update(['abonnement_fin' => now()->addMonth()]);

        $this->artisan('abonnements:expirer')->assertSuccessful();

        $this->assertSame('expire', $echu->fresh()->statut_abonnement);
        $this->assertSame('actif', $valide->fresh()->statut_abonnement);
    }
}
