<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NonAuthentifieTest extends TestCase
{
    use RefreshDatabase;

    public function test_requete_sans_token_recoit_401_json(): void
    {
        $this->getJson('/api/me')->assertStatus(401)->assertJsonPath('message', 'Non authentifié.');
        $this->getJson('/api/etudiants')->assertStatus(401);
    }
}
