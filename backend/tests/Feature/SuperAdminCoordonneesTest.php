<?php

namespace Tests\Feature;

use App\Models\Etablissement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Super admin : e-mail et ville de l'école à la création, modification et dans la liste. */
class SuperAdminCoordonneesTest extends TestCase
{
    use RefreshDatabase;

    public function test_creation_enregistre_ville_et_email_de_l_ecole(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'super_admin', 'etablissement_id' => null]));

        $this->postJson('/api/super-admin/etablissements', [
            'nom' => 'Collège Test', 'pays' => 'TD', 'plan' => 'vip', 'demarrage' => 'essai', 'jours_essai' => 30,
            'ville' => 'N’Djamena', 'email' => 'contact@college-test.test', 'telephone' => '+235 66 00 00 00',
            'admin_name' => 'Admin Test', 'admin_email' => 'admin.college@test.test',
        ])->assertCreated();

        $etab = Etablissement::withoutGlobalScopes()->where('nom', 'Collège Test')->firstOrFail();
        $this->assertSame('N’Djamena', $etab->ville);
        $this->assertSame('contact@college-test.test', $etab->email);
    }

    public function test_la_liste_expose_pays_ville_et_email(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'super_admin', 'etablissement_id' => null]));
        Etablissement::create(['nom' => 'École Liste', 'pays' => 'SN', 'ville' => 'Dakar', 'email' => 'ecole@liste.test', 'plan' => 'standard', 'statut_abonnement' => 'actif']);

        $ecole = collect($this->getJson('/api/super-admin/etablissements')->assertOk()->json('data'))->firstWhere('nom', 'École Liste');
        $this->assertSame('SN', $ecole['pays']);
        $this->assertSame('Dakar', $ecole['ville']);
        $this->assertSame('ecole@liste.test', $ecole['email']);
    }
}
