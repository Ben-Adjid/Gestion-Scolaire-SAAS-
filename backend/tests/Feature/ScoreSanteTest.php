<?php

namespace Tests\Feature;

use App\Models\Etablissement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Score de santé : 25 points par critère, visible dans la fiche de l'école. */
class ScoreSanteTest extends TestCase
{
    use RefreshDatabase;

    public function test_ecole_sans_activite_a_un_score_faible_et_l_abonnement_actif_compte(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'super_admin', 'etablissement_id' => null]));
        $etab = Etablissement::create(['nom' => 'Calme', 'plan' => 'standard', 'statut_abonnement' => 'actif', 'abonnement_fin' => now()->addMonths(3)]);

        $this->getJson("/api/super-admin/etablissements/{$etab->id}")
            ->assertOk()
            ->assertJsonPath('sante.score', 25)
            ->assertJsonCount(4, 'sante.criteres');
    }

    public function test_ecole_suspendue_sans_activite_n_a_aucun_point(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'super_admin', 'etablissement_id' => null]));
        $etab = Etablissement::create(['nom' => 'Suspendue', 'plan' => 'standard', 'statut_abonnement' => 'suspendu']);

        $this->getJson("/api/super-admin/etablissements/{$etab->id}")->assertJsonPath('sante.score', 0);
    }
}
