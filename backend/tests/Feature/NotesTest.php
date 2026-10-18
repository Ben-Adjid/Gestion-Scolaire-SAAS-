<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\Creneau;
use App\Models\EmploiDuTemps;
use App\Models\Enseignant;
use App\Models\Etudiant;
use App\Models\Matiere;
use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotesTest extends TestCase
{
    use RefreshDatabase;

    private $enseignant;
    private $classe;
    private $etudiant;
    private $matiere;

    protected function setUp(): void
    {
        parent::setUp();

        $profil = Enseignant::factory()->create();
        $this->enseignant = User::factory()->create(['role' => 'enseignant', 'enseignant_id' => $profil->id]);
        $this->classe = Classe::factory()->create();
        $this->etudiant = Etudiant::factory()->create(['classe_id' => $this->classe->id]);
        $this->matiere = Matiere::factory()->create();

        $profil->classes()->syncWithoutDetaching([$this->classe->id]);
        $profil->matieres()->syncWithoutDetaching([$this->matiere->id]);

        $creneau = Creneau::create(['heure_debut' => '08:00', 'heure_fin' => '09:00', 'ordre' => 1]);
        EmploiDuTemps::create([
            'classe_id' => $this->classe->id,
            'matiere_id' => $this->matiere->id,
            'enseignant_id' => $profil->id,
            'jour' => 1,
            'creneau_id' => $creneau->id,
        ]);
    }

    public function test_enseignant_peut_saisir_notes()
    {
        $this->actingAs($this->enseignant)
            ->postJson('/api/notes', [
                'etudiant_id' => $this->etudiant->id,
                'matiere_id' => $this->matiere->id,
                'valeur' => 15.5,
                'trimestre' => 1,
            ])
            ->assertStatus(201)
            ->assertJsonPath('note.valeur', 15.5);

        $this->assertDatabaseHas('notes', [
            'etudiant_id' => $this->etudiant->id,
            'valeur' => 15.5,
        ]);
    }

    public function test_note_invalide_rejetee()
    {
        $this->actingAs($this->enseignant)
            ->postJson('/api/notes', [
                'etudiant_id' => $this->etudiant->id,
                'matiere_id' => $this->matiere->id,
                'valeur' => 25, // > 20
                'trimestre' => 1,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('valeur');
    }

    public function test_saisie_groupee_notes()
    {
        $etudiant2 = Etudiant::factory()->create(['classe_id' => $this->classe->id]);

        $this->actingAs($this->enseignant)
            ->postJson('/api/notes/bulk', [
                'matiere_id' => $this->matiere->id,
                'trimestre' => 1,
                'type' => 'devoir',
                'valider' => false,
                'notes' => [
                    ['etudiant_id' => $this->etudiant->id, 'valeur' => 15],
                    ['etudiant_id' => $etudiant2->id, 'valeur' => 12],
                ],
            ])
            ->assertStatus(200)
            ->assertJsonPath('count', 2);
    }
}
