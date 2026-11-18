<?php

namespace Tests\Feature;

use App\Models\Etablissement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Position de l'école (base du contrôle de pointage) : saisie et bornes. */
class PositionEcoleTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $etab = Etablissement::create(['nom' => 'Pos', 'plan' => 'vip', 'statut_abonnement' => 'actif', 'annee_scolaire' => '2026-2027']);
        $user = User::factory()->create(['role' => 'admin', 'etablissement_id' => $etab->id]);
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_l_admin_enregistre_la_position_et_le_rayon(): void
    {
        $user = $this->admin();

        $this->postJson('/api/etablissement', [
            'nom' => 'Pos', 'annee_scolaire' => '2026-2027',
            'latitude' => '12.134500', 'longitude' => '15.055700', 'rayon_pointage' => 200,
        ])->assertOk();

        $etab = $user->etablissement->fresh();
        $this->assertEqualsWithDelta(12.1345, $etab->latitude, 0.00001);
        $this->assertSame(200, $etab->rayon_pointage);
    }

    public function test_les_coordonnees_hors_bornes_sont_refusees(): void
    {
        $this->admin();

        $this->postJson('/api/etablissement', ['nom' => 'Pos', 'annee_scolaire' => '2026-2027', 'latitude' => 200])
            ->assertUnprocessable();
        $this->postJson('/api/etablissement', ['nom' => 'Pos', 'annee_scolaire' => '2026-2027', 'rayon_pointage' => 5000])
            ->assertUnprocessable();
    }
}
