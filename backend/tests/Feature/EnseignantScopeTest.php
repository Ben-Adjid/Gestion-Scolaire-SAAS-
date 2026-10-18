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
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EnseignantScopeTest extends TestCase
{
    use RefreshDatabase;

    private function makeEnseignantUser(int $classeId, array $matiereIds): User
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

    public function test_enseignant_ne_peut_pas_saisir_une_note_d_une_matiere_non_assignee(): void
    {
        $classe = Classe::factory()->create();
        $etudiant = Etudiant::factory()->create(['classe_id' => $classe->id]);
        $math = Matiere::factory()->create(['nom' => 'Maths']);
        $philo = Matiere::factory()->create(['nom' => 'Philosophie']);

        // L'enseignant n'enseigne que les Maths
        $enseignant = $this->makeEnseignantUser($classe->id, [$math->id]);

        Sanctum::actingAs($enseignant);

        $this->postJson('/api/notes', [
            'etudiant_id' => $etudiant->id,
            'matiere_id' => $philo->id,
            'trimestre' => 1,
            'type' => 'devoir',
            'valeur' => 12,
        ])->assertStatus(403);
    }

    public function test_enseignant_ne_voit_que_ses_matieres(): void
    {
        $classe = Classe::factory()->create();
        Etudiant::factory()->create(['classe_id' => $classe->id]);
        $math = Matiere::factory()->create(['nom' => 'Maths']);
        Matiere::factory()->create(['nom' => 'Anglais']);

        $enseignant = $this->makeEnseignantUser($classe->id, [$math->id]);

        Sanctum::actingAs($enseignant);

        $this->getJson('/api/matieres?per_page=100')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nom', 'Maths');
    }

    public function test_enseignant_ne_peut_pas_modifier_une_note_d_une_matiere_non_assignee(): void
    {
        $classe = Classe::factory()->create();
        $etudiant = Etudiant::factory()->create(['classe_id' => $classe->id]);
        $math = Matiere::factory()->create(['nom' => 'Maths']);
        $philo = Matiere::factory()->create(['nom' => 'Philosophie']);

        $enseignant = $this->makeEnseignantUser($classe->id, [$math->id]);

        $note = Note::create([
            'etudiant_id' => $etudiant->id,
            'matiere_id' => $philo->id,
            'trimestre' => 1,
            'type' => 'devoir',
            'valeur' => 12,
            'statut' => 'brouillon',
        ]);

        Sanctum::actingAs($enseignant);

        $this->putJson("/api/notes/{$note->id}", [
            'etudiant_id' => $etudiant->id,
            'matiere_id' => $philo->id,
            'trimestre' => 1,
            'type' => 'devoir',
            'valeur' => 15,
        ])->assertStatus(403);
    }

    public function test_enseignant_ne_peut_pas_supprimer_une_note_d_une_matiere_non_assignee(): void
    {
        $classe = Classe::factory()->create();
        $etudiant = Etudiant::factory()->create(['classe_id' => $classe->id]);
        $math = Matiere::factory()->create(['nom' => 'Maths']);
        $philo = Matiere::factory()->create(['nom' => 'Philosophie']);

        $enseignant = $this->makeEnseignantUser($classe->id, [$math->id]);

        $note = Note::create([
            'etudiant_id' => $etudiant->id,
            'matiere_id' => $philo->id,
            'trimestre' => 1,
            'type' => 'devoir',
            'valeur' => 12,
            'statut' => 'brouillon',
        ]);

        Sanctum::actingAs($enseignant);

        $this->deleteJson("/api/notes/{$note->id}")->assertStatus(403);
    }

    public function test_enseignant_ne_peut_pas_voir_le_bulletin_d_un_eleve_hors_de_ses_classes(): void
    {
        $classeMine = Classe::factory()->create();
        $classeAutre = Classe::factory()->create();
        $etudiantMine = Etudiant::factory()->create(['classe_id' => $classeMine->id]);
        $etudiantAutre = Etudiant::factory()->create(['classe_id' => $classeAutre->id]);
        $math = Matiere::factory()->create();

        $enseignant = $this->makeEnseignantUser($classeMine->id, [$math->id]);

        Sanctum::actingAs($enseignant);

        $this->getJson("/api/etudiants/{$etudiantAutre->id}/bulletin?trimestre=1")
            ->assertStatus(403);
    }

    public function test_enseignant_ne_peut_pas_voir_le_detail_d_un_eleve_hors_de_ses_classes(): void
    {
        $classeMine = Classe::factory()->create();
        $classeAutre = Classe::factory()->create();
        $etudiantMine = Etudiant::factory()->create(['classe_id' => $classeMine->id]);
        $etudiantAutre = Etudiant::factory()->create(['classe_id' => $classeAutre->id]);
        $math = Matiere::factory()->create();

        $enseignant = $this->makeEnseignantUser($classeMine->id, [$math->id]);

        Sanctum::actingAs($enseignant);

        $this->getJson("/api/etudiants/{$etudiantAutre->id}")->assertStatus(403);
        $this->getJson("/api/etudiants/{$etudiantMine->id}")->assertOk();
    }
}
