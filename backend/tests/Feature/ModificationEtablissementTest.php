<?php

namespace Tests\Feature;

use App\Models\Etablissement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Modifier une école depuis la plateforme ne doit pas casser ses données obligatoires. */
class ModificationEtablissementTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_prefixe_vide_garde_la_valeur_actuelle(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'super_admin', 'etablissement_id' => null]));
        $etab = Etablissement::create(['nom' => 'Avant', 'plan' => 'vip', 'statut_abonnement' => 'actif', 'prefixe_matricule' => 'ABC']);

        $this->putJson("/api/super-admin/etablissements/{$etab->id}", [
            'nom' => 'Après', 'plan' => 'vip', 'prefixe_matricule' => '',
        ])->assertOk();

        $this->assertSame('Après', $etab->fresh()->nom);
        $this->assertSame('ABC', $etab->fresh()->prefixe_matricule);
    }

    public function test_la_date_de_fin_est_au_format_iso_court(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'super_admin', 'etablissement_id' => null]));
        $etab = Etablissement::create(['nom' => 'Date', 'plan' => 'standard', 'statut_abonnement' => 'actif', 'abonnement_fin' => '2027-02-05']);

        $this->getJson("/api/super-admin/etablissements/{$etab->id}")
            ->assertJsonPath('abonnement_fin', '2027-02-05');
    }
}
