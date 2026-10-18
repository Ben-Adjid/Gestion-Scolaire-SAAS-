<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_avec_identifiants_valides(): void
    {
        $user = User::factory()->create(['email' => 'admin@test.com']);

        $this->postJson('/api/login', [
            'email' => 'admin@test.com',
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonStructure(['token', 'user']);
    }

    public function test_login_accepte_un_espace_autour_de_l_email(): void
    {
        User::factory()->create(['email' => 'admin@test.com']);

        $this->postJson('/api/login', [
            'email' => '  admin@test.com  ',
            'password' => 'password',
        ])->assertOk();
    }

    public function test_login_avec_mauvais_mot_de_passe(): void
    {
        User::factory()->create(['email' => 'admin@test.com']);

        $this->postJson('/api/login', [
            'email' => 'admin@test.com',
            'password' => 'mauvais',
        ])->assertStatus(422);
    }

    public function test_acces_non_authentifie_refuse(): void
    {
        $this->getJson('/api/dashboard')->assertStatus(401);
    }
}
