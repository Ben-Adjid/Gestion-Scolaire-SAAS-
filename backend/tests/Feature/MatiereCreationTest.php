<?php

namespace Tests\Feature;

use App\Models\Etablissement;
use App\Models\Matiere;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MatiereCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_creation_sans_code_genere_un_code_et_accepte_les_coefficients_jusqua_20(): void
    {
        $etablissement = Etablissement::factory()->create();
        Sanctum::actingAs(User::factory()->create([
            'role' => 'admin',
            'etablissement_id' => $etablissement->id,
        ]));

        $this->postJson('/api/matieres', [
            'nom' => 'Mathématiques',
            'coefficient' => 10,
            'couleur' => '#1F5C4A',
        ])
            ->assertCreated()
            ->assertJsonPath('code', 'MATHEMATIQUES')
            ->assertJsonPath('coefficient', 10);
    }

    public function test_codes_generes_sont_uniques_meme_si_les_noms_se_normalisent_de_la_meme_facon(): void
    {
        $etablissement = Etablissement::factory()->create();
        Sanctum::actingAs(User::factory()->create([
            'role' => 'admin',
            'etablissement_id' => $etablissement->id,
        ]));

        $this->postJson('/api/matieres', ['nom' => 'Économie', 'coefficient' => 1])
            ->assertCreated()
            ->assertJsonPath('code', 'ECONOMIE');

        $this->postJson('/api/matieres', ['nom' => 'Economie', 'coefficient' => 1])
            ->assertCreated()
            ->assertJsonPath('code', 'ECONOMIE-2');

        $this->assertSame(2, Matiere::withoutGlobalScopes()->whereIn('code', ['ECONOMIE', 'ECONOMIE-2'])->count());
    }
}
