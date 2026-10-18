<?php

namespace Tests\Feature;

use App\Models\Absence;
use App\Models\Classe;
use App\Models\Etudiant;
use App\Models\Inscription;
use App\Models\Matiere;
use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ShowEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_peut_consulter_le_detail_des_ressources(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $classe = Classe::factory()->create();
        $etudiant = Etudiant::factory()->create(['classe_id' => $classe->id]);
        $matiere = Matiere::factory()->create();

        $note = Note::create([
            'etudiant_id' => $etudiant->id,
            'matiere_id' => $matiere->id,
            'trimestre' => 1,
            'type' => 'devoir',
            'valeur' => 15,
        ]);

        $inscription = Inscription::create([
            'etudiant_id' => $etudiant->id,
            'classe_id' => $classe->id,
            'annee_scolaire' => '2025-2026',
            'date_inscription' => '2025-09-01',
            'statut' => 'active',
            'type' => 'nouveau',
        ]);

        $absence = Absence::create([
            'etudiant_id' => $etudiant->id,
            'date' => '2025-10-01',
            'type' => 'absence',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson("/api/notes/{$note->id}")->assertOk();
        $this->getJson("/api/inscriptions/{$inscription->id}")->assertOk();
        $this->getJson("/api/absences/{$absence->id}")->assertOk();
    }
}
