<?php

namespace Tests\Feature;

use App\Models\AbonnementPaiement;
use App\Models\Etablissement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Essai gratuit (30 jours) et facture d'abonnement, pour la plateforme. */
class EssaiEtFactureTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_essai_donne_acces_pendant_30_jours(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'super_admin', 'etablissement_id' => null]));
        $etab = Etablissement::create(['nom' => 'Essai', 'plan' => 'standard', 'statut_abonnement' => 'expire']);

        $this->postJson("/api/super-admin/etablissements/{$etab->id}/essai")->assertOk();

        $etab->refresh();
        $this->assertSame('essai', $etab->statut_abonnement);
        $this->assertTrue($etab->isActif());
        $this->assertSame(now()->addDays(30)->toDateString(), $etab->abonnement_fin->toDateString());
        $this->assertDatabaseHas('audits', ['action' => 'essai_gratuit']);
    }

    public function test_la_facture_reprend_le_montant_de_l_abonnement(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'super_admin', 'etablissement_id' => null]));
        $etab = Etablissement::create(['nom' => 'Facturé', 'plan' => 'vip', 'statut_abonnement' => 'actif']);
        $paiement = AbonnementPaiement::create([
            'etablissement_id' => $etab->id, 'plan' => 'vip', 'montant' => 150000, 'mode_paiement' => 'virement',
            'periode_debut' => '2026-11-05', 'periode_fin' => '2027-02-05',
        ]);

        $this->get("/api/super-admin/paiements-abonnement/{$paiement->id}/facture")
            ->assertOk()
            ->assertSee('Facture', false)
            ->assertSee('150 000 FCFA', false);
    }
}
