<?php

namespace Tests\Feature;

use App\Models\AbsenceEnseignant;
use App\Models\Enseignant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AbsenceEnseignantSuppressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_signalement_d_absence_enseignant_supprimable(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $absence = AbsenceEnseignant::create([
            'enseignant_id' => Enseignant::factory()->create()->id,
            'date' => '2026-01-10',
            'justifie' => false,
            'etablissement_id' => 1,
        ]);

        $this->deleteJson("/api/absences-enseignants/{$absence->id}")->assertOk();
        $this->assertNull(AbsenceEnseignant::find($absence->id));
    }
}
