<?php

namespace Tests\Feature;

use App\Models\Etablissement;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Permissions personnalisées (VIP et plus) et dépenses de l'établissement (Entreprise). */
class DepensesEtPermissionsFormuleTest extends TestCase
{
    use RefreshDatabase;

    private function etablissement(string $plan): Etablissement
    {
        return Etablissement::create(['nom' => "Lycée {$plan}", 'plan' => $plan, 'statut_abonnement' => 'actif']);
    }

    public function test_standard_garde_des_roles_fixes_sans_permissions_personnalisees(): void
    {
        $etab = $this->etablissement('standard');
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'etablissement_id' => $etab->id]));

        $base = ['name' => 'Secrétaire', 'email' => 'sec@test.com', 'role' => 'secretaire', 'password' => 'secret123'];

        $this->postJson('/api/users', $base + ['permissions' => ['notes.modifier']])
            ->assertForbidden()
            ->assertJsonPath('code', 'plan_insuffisant');

        $this->postJson('/api/users', $base)->assertCreated();
    }

    public function test_vip_peut_accorder_des_permissions(): void
    {
        $etab = $this->etablissement('vip');
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'etablissement_id' => $etab->id]));

        $cree = $this->postJson('/api/users', [
            'name' => 'Censeur', 'email' => 'cen@test.com', 'role' => 'censeur', 'password' => 'secret123',
            'permissions' => ['notes.modifier'],
        ])->assertCreated()->json('user');

        $this->assertTrue(User::find($cree['id'])->hasPermission('notes.modifier'));
        $this->assertSame(1, Permission::where('code', 'notes.modifier')->count());
    }

    public function test_depenses_reservees_a_entreprise_et_deduites_du_resultat(): void
    {
        $vip = $this->etablissement('vip');
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'etablissement_id' => $vip->id]));
        $this->postJson('/api/depenses', ['categorie' => 'salaires', 'libelle' => 'Paie', 'montant' => 500000, 'date' => '2026-11-01'])
            ->assertForbidden();

        $entreprise = $this->etablissement('entreprise');
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'etablissement_id' => $entreprise->id]));
        $this->postJson('/api/depenses', ['categorie' => 'salaires', 'libelle' => 'Paie', 'montant' => 500000, 'date' => '2026-11-01'])
            ->assertCreated();
        $this->postJson('/api/depenses', ['categorie' => 'fournitures', 'libelle' => 'Craie', 'montant' => 20000, 'date' => '2026-11-02'])
            ->assertCreated();

        $synthese = $this->getJson('/api/comptabilite/synthese')->assertOk()->json();
        $this->assertSame(520000.0, (float) $synthese['depenses']['total']);
        $this->assertSame(-520000.0, (float) $synthese['depenses']['resultat_net']);
        $this->assertCount(2, $synthese['depenses']['par_categorie']);
    }
}
