<?php

namespace Tests\Feature;

use App\Models\Etablissement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/** Après cinq mots de passe faux, le compte est verrouillé 15 minutes, même avec le bon mot de passe. */
class VerrouillageConnexionTest extends TestCase
{
    use RefreshDatabase;

    public function test_compte_verrouille_apres_cinq_echecs(): void
    {
        RateLimiter::clear('connexion-echecs:verrou@test.com');
        $etab = Etablissement::create(['nom' => 'Verrou', 'plan' => 'standard', 'statut_abonnement' => 'actif']);
        User::factory()->create(['role' => 'admin', 'etablissement_id' => $etab->id, 'email' => 'verrou@test.com', 'password' => Hash::make('bon-mdp-1')]);
        $this->withoutMiddleware(ThrottleRequests::class);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', ['email' => 'verrou@test.com', 'password' => 'faux'])->assertUnprocessable();
        }

        $this->postJson('/api/login', ['email' => 'verrou@test.com', 'password' => 'bon-mdp-1'])
            ->assertStatus(429)
            ->assertJsonPath('code', 'compte_verrouille');

        RateLimiter::clear('connexion-echecs:verrou@test.com');
    }
}
