<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\Enseignant;
use App\Models\Etudiant;
use App\Models\Matiere;
use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NoteTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_devoir_ne_ecrase_pas_une_composition_validee(): void
    {
        $classe = Classe::factory()->create();
        $matiere = Matiere::factory()->create();
        $eleve = Etudiant::factory()->create(['classe_id' => $classe->id]);
        $profil = Enseignant::factory()->create();
        $enseignant = User::factory()->create(['role' => 'enseignant', 'enseignant_id' => $profil->id]);
        $profil->classes()->attach($classe->id);
        $profil->matieres()->attach($matiere->id);

        $composition = Note::create([
            'etudiant_id' => $eleve->id, 'matiere_id' => $matiere->id, 'trimestre' => 1,
            'type' => 'composition', 'valeur' => 10, 'statut' => 'valide',
        ]);

        Sanctum::actingAs($enseignant);
        $this->postJson('/api/notes/bulk', [
            'matiere_id' => $matiere->id,
            'trimestre' => 1,
            'type' => 'devoir',
            'valider' => false,
            'notes' => [['etudiant_id' => $eleve->id, 'valeur' => 14]],
        ])->assertOk();

        $this->assertEquals(10, (float) $composition->fresh()->valeur);
        $this->assertEquals(14, (float) Note::where(['etudiant_id' => $eleve->id, 'type' => 'devoir'])->value('valeur'));
    }

    public function test_refus_d_une_note_validee_renvoie_un_message_en_francais(): void
    {
        $classe = Classe::factory()->create();
        $matiere = Matiere::factory()->create();
        $eleve = Etudiant::factory()->create(['classe_id' => $classe->id]);
        $profil = Enseignant::factory()->create();
        $enseignant = User::factory()->create(['role' => 'enseignant', 'enseignant_id' => $profil->id]);
        $profil->classes()->attach($classe->id);
        $profil->matieres()->attach($matiere->id);

        Note::create([
            'etudiant_id' => $eleve->id, 'matiere_id' => $matiere->id, 'trimestre' => 1,
            'type' => 'devoir', 'valeur' => 10, 'statut' => 'valide',
        ]);

        Sanctum::actingAs($enseignant);
        $this->postJson('/api/notes/bulk', [
            'matiere_id' => $matiere->id,
            'trimestre' => 1,
            'type' => 'devoir',
            'valider' => false,
            'notes' => [['etudiant_id' => $eleve->id, 'valeur' => 5]],
        ])->assertUnprocessable()->assertJsonPath('errors.notes.0', "La note de l'élève n° " . $eleve->id . " est déjà validée et verrouillée : aucune note de la saisie n'a été enregistrée.");
    }
}
