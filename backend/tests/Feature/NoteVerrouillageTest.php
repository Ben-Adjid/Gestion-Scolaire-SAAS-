<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\Creneau;
use App\Models\EmploiDuTemps;
use App\Models\Enseignant;
use App\Models\Etudiant;
use App\Models\Matiere;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NoteVerrouillageTest extends TestCase
{
    use RefreshDatabase;

    private function makeEnseignantUser(array $matiereIds = [], ?int $classeId = null): User
    {
        $enseignant = Enseignant::factory()->create();
        $user = User::factory()->create(['role' => 'enseignant', 'enseignant_id' => $enseignant->id]);

        $creneau = Creneau::firstOrCreate(
            ['ordre' => 1],
            ['heure_debut' => '08:00', 'heure_fin' => '09:00', 'ordre' => 1]
        );

        $enseignant->classes()->syncWithoutDetaching([$classeId]);
        $enseignant->matieres()->syncWithoutDetaching($matiereIds);

        foreach ($matiereIds as $matiereId) {
            EmploiDuTemps::create([
                'classe_id' => $classeId,
                'matiere_id' => $matiereId,
                'enseignant_id' => $enseignant->id,
                'jour' => 1,
                'creneau_id' => $creneau->id,
            ]);
        }

        return $user;
    }

    public function test_enseignant_valide_les_notes_puis_ne_peut_plus_les_modifier(): void
    {
        $classe = Classe::factory()->create();
        $etudiant = Etudiant::factory()->create(['classe_id' => $classe->id]);
        $matiere = Matiere::factory()->create();

        $enseignant = $this->makeEnseignantUser([$matiere->id], $classe->id);
        $admin = User::factory()->create(['role' => 'admin']);

        Sanctum::actingAs($enseignant);

        // Valide une note
        $this->postJson('/api/notes/bulk', [
            'matiere_id' => $matiere->id,
            'trimestre' => 1,
            'type' => 'devoir',
            'valider' => true,
            'notes' => [['etudiant_id' => $etudiant->id, 'valeur' => 15]],
        ])->assertOk();

        // Tentative de re-modification par l'enseignant -> 403
        $this->postJson('/api/notes/bulk', [
            'matiere_id' => $matiere->id,
            'trimestre' => 1,
            'type' => 'devoir',
            'valider' => false,
            'notes' => [['etudiant_id' => $etudiant->id, 'valeur' => 5]],
        ])->assertStatus(403);

        // Le proviseur peut modifier une note validée
        Sanctum::actingAs(User::factory()->create(['role' => 'proviseur']));

        $this->postJson('/api/notes/bulk', [
            'matiere_id' => $matiere->id,
            'trimestre' => 1,
            'type' => 'devoir',
            'valider' => false,
            'notes' => [['etudiant_id' => $etudiant->id, 'valeur' => 5]],
        ])->assertOk();
    }

    public function test_note_avec_appreciation_auto_generée(): void
    {
        $classe = Classe::factory()->create();
        $etudiant = Etudiant::factory()->create(['classe_id' => $classe->id]);
        $matiere = Matiere::factory()->create();
        $enseignant = $this->makeEnseignantUser([$matiere->id], $classe->id);

        Sanctum::actingAs($enseignant);

        $this->postJson('/api/notes', [
            'etudiant_id' => $etudiant->id,
            'matiere_id' => $matiere->id,
            'trimestre' => 1,
            'type' => 'devoir',
            'valeur' => 17,
        ])->assertCreated()
            ->assertJsonPath('note.appreciation', 'Excellent');
    }
}
