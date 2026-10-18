<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\Creneau;
use App\Models\EmploiDuTemps;
use App\Models\Enseignant;
use App\Models\Etablissement;
use App\Models\Etudiant;
use App\Models\Matiere;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EtudiantScopeTest extends TestCase
{
    use RefreshDatabase;

    private Classe $classeAssignee;
    private Classe $classeAutre;
    private Etudiant $eleveAssigne;
    private Etudiant $eleveAutre;
    private User $enseignant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->classeAssignee = Classe::factory()->create();
        $this->classeAutre = Classe::factory()->create();
        $this->eleveAssigne = Etudiant::factory()->create(['classe_id' => $this->classeAssignee->id]);
        $this->eleveAutre = Etudiant::factory()->create(['classe_id' => $this->classeAutre->id]);

        $profil = Enseignant::factory()->create();
        $this->enseignant = User::factory()->create(['role' => 'enseignant', 'enseignant_id' => $profil->id]);

        $profil->classes()->syncWithoutDetaching([$this->classeAssignee->id]);

        EmploiDuTemps::create([
            'classe_id' => $this->classeAssignee->id,
            'matiere_id' => Matiere::factory()->create()->id,
            'enseignant_id' => $profil->id,
            'jour' => 1,
            'creneau_id' => Creneau::create(['heure_debut' => '08:00', 'heure_fin' => '09:00', 'ordre' => 1])->id,
        ]);
    }

    public function test_enseignant_ne_liste_que_les_eleves_de_ses_classes(): void
    {
        Sanctum::actingAs($this->enseignant);

        $ids = collect($this->getJson('/api/etudiants')->assertOk()->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($this->eleveAssigne->id));
        $this->assertFalse($ids->contains($this->eleveAutre->id));
    }

    public function test_enseignant_ne_voit_pas_le_detail_d_un_eleve_hors_de_ses_classes(): void
    {
        Sanctum::actingAs($this->enseignant);

        $this->getJson("/api/etudiants/{$this->eleveAutre->id}")->assertForbidden();
        $this->getJson("/api/etudiants/{$this->eleveAssigne->id}")->assertOk();
    }

    public function test_gestionnaire_voit_tous_les_eleves_mais_sans_notes_ni_absences(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'gestionnaire']));

        $ids = collect($this->getJson('/api/etudiants')->assertOk()->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($this->eleveAutre->id));

        $this->getJson("/api/etudiants/{$this->eleveAutre->id}")
            ->assertOk()
            ->assertJsonMissingPath('notes')
            ->assertJsonMissingPath('absences');
    }

    public function test_recherche_ne_franchit_pas_le_perimetre_de_l_etablissement(): void
    {
        $autre = Etablissement::create(['nom' => 'Autre établissement']);
        Etudiant::factory()->create([
            'etablissement_id' => $autre->id,
            'nom' => 'ZzzIntrus',
            'prenom' => 'Test',
            'classe_id' => Classe::factory()->create(['etablissement_id' => $autre->id])->id,
        ]);

        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'etablissement_id' => $this->eleveAssigne->etablissement_id]));

        $this->getJson('/api/etudiants?search=ZzzIntrus')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
