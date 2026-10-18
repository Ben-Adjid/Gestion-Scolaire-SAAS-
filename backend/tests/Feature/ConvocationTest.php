<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\Etudiant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConvocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_peut_enregistrer_une_convocation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $etudiant = Etudiant::factory()->create();

        Sanctum::actingAs($admin);

        $this->postJson('/api/convocations', [
            'etudiant_id' => $etudiant->id,
            'date' => '2025-10-01',
            'motif' => 'Discipline',
        ])->assertCreated();

        $this->assertDatabaseHas('convocations', [
            'etudiant_id' => $etudiant->id,
            'motif' => 'Discipline',
        ]);
    }

    public function test_enseignant_ne_peut_pas_enregistrer_une_convocation(): void
    {
        $enseignant = User::factory()->create(['role' => 'enseignant']);
        $etudiant = Etudiant::factory()->create();

        Sanctum::actingAs($enseignant);

        $this->postJson('/api/convocations', [
            'etudiant_id' => $etudiant->id,
            'date' => '2025-10-01',
        ])->assertStatus(403);
    }

    public function test_bulletin_inclut_les_convocations_et_motifs(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $classe = Classe::factory()->create();
        $etudiant = Etudiant::factory()->create(['classe_id' => $classe->id]);

        \App\Models\Convocation::create([
            'etudiant_id' => $etudiant->id,
            'date' => '2025-10-01',
            'motif' => 'Discipline',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson("/api/etudiants/{$etudiant->id}/bulletin?trimestre=1")
            ->assertOk()
            ->assertJsonPath('nb_convocations', 1)
            ->assertJsonPath('motifs_convocations.0', 'Discipline');
    }
}
