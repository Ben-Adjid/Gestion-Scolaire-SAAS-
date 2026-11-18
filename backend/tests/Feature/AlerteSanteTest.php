<?php

namespace Tests\Feature;

use App\Mail\AlerteSanteEcole;
use App\Models\Etablissement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Alerte plateforme quand une école active tombe sous le seuil de santé. */
class AlerteSanteTest extends TestCase
{
    use RefreshDatabase;

    public function test_une_ecole_inactive_declenche_une_alerte_a_la_plateforme(): void
    {
        Mail::fake();
        User::factory()->create(['role' => 'super_admin', 'etablissement_id' => null, 'email' => 'plateforme@test.com']);
        Etablissement::create(['nom' => 'Endormie', 'plan' => 'standard', 'statut_abonnement' => 'actif', 'abonnement_fin' => now()->addMonths(3)]);

        $this->artisan('sante:alertes')->assertSuccessful();

        Mail::assertQueued(AlerteSanteEcole::class, fn ($m) => $m->hasTo('plateforme@test.com') && str_contains($m->render(), 'Endormie'));
    }

    public function test_une_ecole_suspendue_ne_declenche_aucune_alerte(): void
    {
        Mail::fake();
        User::factory()->create(['role' => 'super_admin', 'etablissement_id' => null, 'email' => 'plateforme2@test.com']);
        Etablissement::create(['nom' => 'Suspendue', 'plan' => 'standard', 'statut_abonnement' => 'suspendu']);

        $this->artisan('sante:alertes')->assertSuccessful();

        Mail::assertNotQueued(AlerteSanteEcole::class, fn ($m) => str_contains($m->render(), 'Suspendue'));
    }
}
