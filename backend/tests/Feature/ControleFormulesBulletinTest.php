<?php

namespace Tests\Feature;

use App\Models\Etablissement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Contrôle par formule : la personnalisation des bulletins est réservée à la formule VIP et plus. */
class ControleFormulesBulletinTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $plan): User
    {
        $etab = Etablissement::create(['nom' => 'École '.$plan, 'plan' => $plan, 'statut_abonnement' => 'actif', 'annee_scolaire' => '2026-2027']);
        $admin = User::factory()->create(['role' => 'admin', 'etablissement_id' => $etab->id]);
        Sanctum::actingAs($admin);

        return $admin;
    }

    public function test_standard_ne_peut_pas_personnaliser_les_bulletins(): void
    {
        $this->admin('standard');
        $this->postJson('/api/etablissement', ['nom' => 'École standard', 'annee_scolaire' => '2026-2027', 'bulletin_signature_nom' => 'Awa Diop'])->assertOk();
        $this->assertNull(Etablissement::where('nom', 'École standard')->value('bulletin_signature_nom'));
    }

    public function test_vip_personnalise_les_bulletins(): void
    {
        $this->admin('vip');
        $this->postJson('/api/etablissement', ['nom' => 'École vip', 'annee_scolaire' => '2026-2027', 'bulletin_signature_nom' => 'Awa Diop'])->assertOk();
        $this->assertSame('Awa Diop', Etablissement::where('nom', 'École vip')->value('bulletin_signature_nom'));
    }
}
