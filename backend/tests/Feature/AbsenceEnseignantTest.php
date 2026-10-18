<?php

namespace Tests\Feature;

use App\Models\Enseignant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AbsenceEnseignantTest extends TestCase
{
    use RefreshDatabase;

    public function test_enseignant_peut_signaler_son_absence(): void
    {
        $enseignant = Enseignant::factory()->create();
        $user = User::factory()->create(['role' => 'enseignant', 'enseignant_id' => $enseignant->id]);

        Sanctum::actingAs($user);

        $this->postJson('/api/absences-enseignants', [
            'date' => '2025-10-01',
            'type' => 'absence',
            'motif' => 'Maladie',
        ])->assertCreated()
            ->assertJsonPath('absence.enseignant_id', $enseignant->id);

        $this->assertDatabaseHas('absences_enseignants', [
            'enseignant_id' => $enseignant->id,
            'type' => 'absence',
        ]);
    }

    public function test_admin_peut_lister_les_absences_profs(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $enseignant = Enseignant::factory()->create();

        \App\Models\AbsenceEnseignant::create([
            'enseignant_id' => $enseignant->id,
            'date' => '2025-10-01',
            'type' => 'retard',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/absences-enseignants')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_enseignant_ne_peut_pas_lister_les_absences_profs(): void
    {
        $enseignant = Enseignant::factory()->create();
        $user = User::factory()->create(['role' => 'enseignant', 'enseignant_id' => $enseignant->id]);

        Sanctum::actingAs($user);

        $this->getJson('/api/absences-enseignants')->assertStatus(403);
    }
}
