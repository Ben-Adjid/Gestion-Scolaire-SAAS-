<?php

namespace Tests\Feature;

use App\Models\AbonnementPaiement;
use App\Models\Etablissement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Vue plateforme du super admin : revenus, renouvellements, encaissements. */
class VuePlateformeTest extends TestCase
{
    use RefreshDatabase;

    public function test_vue_plateforme_calcule_revenus_et_renouvellements(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'super_admin', 'etablissement_id' => null]));

        Etablissement::create(['nom' => 'A', 'plan' => 'standard', 'statut_abonnement' => 'actif', 'abonnement_fin' => now()->addDays(10)]);
        Etablissement::create(['nom' => 'B', 'plan' => 'vip', 'statut_abonnement' => 'actif', 'abonnement_fin' => now()->addDays(200)]);
        Etablissement::create(['nom' => 'C', 'plan' => 'entreprise', 'statut_abonnement' => 'suspendu', 'abonnement_fin' => now()->subDays(3)]);

        AbonnementPaiement::create([
            'etablissement_id' => 1, 'plan' => 'standard', 'montant' => 25000, 'mode_paiement' => 'virement',
            'periode_debut' => now()->toDateString(), 'periode_fin' => now()->addMonth()->toDateString(),
        ]);

        $res = $this->getJson('/api/super-admin/dashboard')->assertOk();

        // Actifs : l'établissement de base de TestCase (vip, 50 000) + standard (25 000). Le suspendu ne compte pas.
        $res->assertJsonPath('stats.mrr_fcfa', 75000 + 50000);
        $res->assertJsonPath('stats.encaisse_30j_fcfa', 25000);
        $res->assertJsonCount(12, 'encaissements');
        $this->assertCount(2, $res->json('renouvellements'));
        $this->assertSame('C', $res->json('renouvellements.0.nom'));
    }
}
