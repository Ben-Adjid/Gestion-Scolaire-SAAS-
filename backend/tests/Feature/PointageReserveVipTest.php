<?php

namespace Tests\Feature;

use App\Models\Etablissement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Pointage des enseignants : réservé aux formules VIP et Entreprise, avec un message clair pour la Standard. */
class PointageReserveVipTest extends TestCase
{
    use RefreshDatabase;

    private function connecter(string $plan): void
    {
        $etab = Etablissement::create(['nom' => 'École '.$plan, 'plan' => $plan, 'statut_abonnement' => 'actif', 'annee_scolaire' => '2026-2027']);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'etablissement_id' => $etab->id]));
    }

    public function test_une_ecole_standard_est_bloquee_avec_le_message_attendu(): void
    {
        $this->connecter('standard');

        $this->getJson('/api/pointages')
            ->assertForbidden()
            ->assertJsonPath('code', 'plan_insuffisant')
            ->assertJsonPath('message', "Cette fonctionnalité nécessite le forfait VIP ou Entreprise. Contactez l'administrateur pour mettre à niveau votre établissement.");
    }

    public function test_une_ecole_vip_accede_au_pointage(): void
    {
        $this->connecter('vip');

        $this->getJson('/api/pointages')->assertOk();
    }
}
