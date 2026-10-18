<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\Convocation;
use App\Models\Enseignant;
use App\Models\Etudiant;
use App\Models\Matiere;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RolesPerimetreTest extends TestCase
{
    use RefreshDatabase;

    private User $enseignant;
    private Classe $classeAssignee;
    private Classe $classeAutre;
    private Etudiant $eleveAssigne;
    private Etudiant $eleveAutre;
    private Matiere $matiere;

    protected function setUp(): void
    {
        parent::setUp();

        $profil = Enseignant::factory()->create(['email' => 'prof@ecole.test', 'telephone' => '770000000']);
        $this->enseignant = User::factory()->create(['role' => 'enseignant', 'enseignant_id' => $profil->id]);

        $this->classeAssignee = Classe::factory()->create();
        $this->classeAutre = Classe::factory()->create();
        $this->matiere = Matiere::factory()->create();

        $profil->classes()->attach($this->classeAssignee->id);
        $profil->matieres()->attach($this->matiere->id);

        $this->eleveAssigne = Etudiant::factory()->create(['classe_id' => $this->classeAssignee->id]);
        $this->eleveAutre = Etudiant::factory()->create(['classe_id' => $this->classeAutre->id]);
    }

    public function test_enseignant_ne_voit_que_ses_classes_sans_liste_d_eleves(): void
    {
        Sanctum::actingAs($this->enseignant);

        $classes = collect($this->getJson('/api/classes')->assertOk()->json('data'));

        $this->assertEquals([$this->classeAssignee->id], $classes->pluck('id')->all());
        $this->assertArrayNotHasKey('etudiants', $classes->first());
    }

    public function test_enseignant_ne_fait_pas_l_appel_d_une_autre_classe(): void
    {
        Sanctum::actingAs($this->enseignant);

        $this->postJson('/api/appels', [
            'classe_id' => $this->classeAutre->id,
            'date' => '2025-10-01',
            'presences' => [
                ['etudiant_id' => $this->eleveAutre->id, 'statut' => 'present'],
            ],
        ])->assertForbidden();
    }

    public function test_enseignant_ne_saisit_pas_d_absence_pour_un_eleve_d_une_autre_classe(): void
    {
        Sanctum::actingAs($this->enseignant);

        $this->postJson('/api/absences', [
            'etudiant_id' => $this->eleveAutre->id,
            'date' => '2025-10-01',
        ])->assertForbidden();

        $this->postJson('/api/absences', [
            'etudiant_id' => $this->eleveAssigne->id,
            'date' => '2025-10-01',
        ])->assertCreated();
    }

    public function test_enseignant_ne_saisit_pas_de_note_pour_une_autre_classe(): void
    {
        Sanctum::actingAs($this->enseignant);

        $this->postJson('/api/notes', [
            'etudiant_id' => $this->eleveAutre->id,
            'matiere_id' => $this->matiere->id,
            'trimestre' => 1,
            'valeur' => 12,
        ])->assertForbidden();
    }

    public function test_saisie_groupee_reservee_aux_enseignants_et_au_proviseur(): void
    {
        $payload = [
            'matiere_id' => $this->matiere->id,
            'trimestre' => 1,
            'notes' => [['etudiant_id' => $this->eleveAssigne->id, 'valeur' => 10]],
        ];

        foreach (['cpe', 'secretaire', 'gestionnaire', 'admin'] as $role) {
            Sanctum::actingAs(User::factory()->create(['role' => $role]));
            $this->postJson('/api/notes/bulk', $payload)->assertForbidden();
        }
    }

    public function test_note_validee_modifiable_seulement_par_le_proviseur(): void
    {
        $note = \App\Models\Note::create([
            'etudiant_id' => $this->eleveAssigne->id,
            'matiere_id' => $this->matiere->id,
            'trimestre' => 1,
            'type' => 'devoir',
            'valeur' => 12,
            'statut' => 'valide',
        ]);
        $payload = ['etudiant_id' => $this->eleveAssigne->id, 'matiere_id' => $this->matiere->id, 'trimestre' => 1, 'valeur' => 14];

        Sanctum::actingAs($this->enseignant);
        $this->putJson("/api/notes/{$note->id}", $payload)->assertForbidden();

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->putJson("/api/notes/{$note->id}", $payload)->assertForbidden();
        $this->deleteJson("/api/notes/{$note->id}")->assertForbidden();

        Sanctum::actingAs(User::factory()->create(['role' => 'proviseur']));
        $this->putJson("/api/notes/{$note->id}", $payload)->assertOk();
        $this->deleteJson("/api/notes/{$note->id}")->assertOk();
    }

    public function test_convocations_reservees_au_personnel_de_vie_scolaire(): void
    {
        Convocation::create(['etudiant_id' => $this->eleveAutre->id, 'date' => '2025-10-01', 'motif' => 'Discipline']);

        Sanctum::actingAs($this->enseignant);
        $this->getJson('/api/convocations')->assertForbidden();

        Sanctum::actingAs(User::factory()->create(['role' => 'cpe']));
        $this->getJson('/api/convocations')->assertOk();
    }

    public function test_emploi_du_temps_refuse_un_enseignant_hors_de_ses_affectations(): void
    {
        $creneau = \App\Models\Creneau::create(['heure_debut' => '08:00', 'heure_fin' => '09:00', 'ordre' => 1]);
        $profil = Enseignant::find($this->enseignant->enseignant_id);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->postJson('/api/emploi-du-temps', [
            'classe_id' => $this->classeAutre->id,
            'matiere_id' => $this->matiere->id,
            'enseignant_id' => $profil->id,
            'jour' => 1,
            'creneau_id' => $creneau->id,
        ])->assertStatus(422);

        $this->postJson('/api/emploi-du-temps', [
            'classe_id' => $this->classeAssignee->id,
            'matiere_id' => $this->matiere->id,
            'enseignant_id' => $profil->id,
            'jour' => 1,
            'creneau_id' => $creneau->id,
        ])->assertCreated();
    }

    public function test_filtres_emploi_du_temps_par_classe_et_par_enseignant(): void
    {
        $creneau1 = \App\Models\Creneau::create(['heure_debut' => '08:00', 'heure_fin' => '09:00', 'ordre' => 1]);
        $creneau2 = \App\Models\Creneau::create(['heure_debut' => '09:00', 'heure_fin' => '10:00', 'ordre' => 2]);
        $autreProfil = Enseignant::factory()->create();
        $profil = Enseignant::find($this->enseignant->enseignant_id);

        \App\Models\EmploiDuTemps::create(['classe_id' => $this->classeAssignee->id, 'matiere_id' => $this->matiere->id, 'enseignant_id' => $profil->id, 'jour' => 1, 'creneau_id' => $creneau1->id]);
        \App\Models\EmploiDuTemps::create(['classe_id' => $this->classeAutre->id, 'matiere_id' => $this->matiere->id, 'enseignant_id' => $autreProfil->id, 'jour' => 1, 'creneau_id' => $creneau2->id]);

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $parEnseignant = $this->getJson("/api/emploi-du-temps?enseignant_id={$autreProfil->id}")->assertOk()->json('emploi_du_temps');
        $this->assertCount(1, $parEnseignant);
        $this->assertEquals($autreProfil->id, $parEnseignant[0]['enseignant_id']);

        $parClasse = $this->getJson("/api/emploi-du-temps?classe_id={$this->classeAssignee->id}")->assertOk()->json('emploi_du_temps');
        $this->assertCount(1, $parClasse);
        $this->assertEquals($this->classeAssignee->id, $parClasse[0]['classe_id']);
    }

    public function test_liste_des_eleves_contient_le_nom_de_la_classe(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $eleve = collect($this->getJson('/api/etudiants?per_page=100')->assertOk()->json('data'))
            ->firstWhere('id', $this->eleveAssigne->id);

        $this->assertEquals($this->classeAssignee->nom, $eleve['classe']['nom']);
    }

    public function test_creation_et_modification_d_eleve_par_role(): void
    {
        $payload = [
            'nom' => 'Diallo',
            'prenom' => 'Awa',
            'date_naissance' => '2012-05-10',
            'genre' => 'F',
            'classe_id' => $this->classeAssignee->id,
        ];

        Sanctum::actingAs(User::factory()->create(['role' => 'censeur']));
        $eleve = $this->postJson('/api/etudiants', $payload)->assertCreated()->json();
        $this->putJson("/api/etudiants/{$eleve['id']}", array_merge($payload, ['prenom' => 'Fatou']))->assertOk();

        Sanctum::actingAs(User::factory()->create(['role' => 'cpe']));
        $this->postJson('/api/etudiants', array_merge($payload, ['nom' => 'Ndiaye']))->assertForbidden();

        Sanctum::actingAs($this->enseignant);
        $this->postJson('/api/etudiants', array_merge($payload, ['nom' => 'Sow']))->assertForbidden();
    }

    public function test_coordonnees_des_enseignants_masquees_pour_un_enseignant(): void
    {
        Sanctum::actingAs($this->enseignant);

        $premier = $this->getJson('/api/enseignants')->assertOk()->json('data.0');
        $this->assertArrayNotHasKey('email', $premier);
        $this->assertArrayNotHasKey('telephone', $premier);

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->assertArrayHasKey('email', $this->getJson('/api/enseignants')->json('data.0'));
    }
}
