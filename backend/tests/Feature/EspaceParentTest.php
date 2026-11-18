<?php

namespace Tests\Feature;

use App\Models\Absence;
use App\Models\Classe;
use App\Models\ConseilClasse;
use App\Models\Etablissement;
use App\Models\Etudiant;
use App\Models\Matiere;
use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EspaceParentTest extends TestCase
{
    use RefreshDatabase;

    private User $parent;
    private Etudiant $enfant1;
    private Etudiant $enfant2;
    private Etudiant $autreEleve;
    private Classe $classe;

    protected function setUp(): void
    {
        parent::setUp();

        $this->classe = Classe::factory()->create();
        $this->enfant1 = Etudiant::factory()->create(['classe_id' => $this->classe->id, 'etablissement_id' => 1]);
        $this->enfant2 = Etudiant::factory()->create(['classe_id' => $this->classe->id, 'etablissement_id' => 1]);
        $this->autreEleve = Etudiant::factory()->create(['classe_id' => $this->classe->id, 'etablissement_id' => 1]);

        $this->parent = User::factory()->create(['role' => 'parent', 'etablissement_id' => 1]);
        $this->parent->enfants()->attach([$this->enfant1->id, $this->enfant2->id]);
    }

    public function test_parent_voit_tous_ses_enfants_du_meme_etablissement(): void
    {
        Sanctum::actingAs($this->parent);

        $ids = collect($this->getJson('/api/parent/enfants')->assertOk()->json('enfants'))->pluck('id')->sort()->values();
        $this->assertEquals([$this->enfant1->id, $this->enfant2->id], $ids->all());
    }

    public function test_parent_ne_voit_pas_l_eleve_d_un_autre_parent(): void
    {
        Sanctum::actingAs($this->parent);

        $this->getJson("/api/parent/enfants/{$this->autreEleve->id}/resume")->assertForbidden();
        $this->getJson("/api/parent/enfants/{$this->enfant2->id}/resume")->assertOk();
    }

    public function test_resume_ne_montre_que_les_notes_validees_et_les_bulletins_publies(): void
    {
        $matiere = Matiere::factory()->create(['coefficient' => 2]);
        Note::create(['etudiant_id' => $this->enfant1->id, 'matiere_id' => $matiere->id, 'trimestre' => 1, 'type' => 'devoir', 'valeur' => 15, 'statut' => 'valide']);
        Note::create(['etudiant_id' => $this->enfant1->id, 'matiere_id' => $matiere->id, 'trimestre' => 1, 'type' => 'composition', 'valeur' => 3, 'statut' => 'brouillon']);

        Sanctum::actingAs($this->parent);
        $resume = $this->getJson("/api/parent/enfants/{$this->enfant1->id}/resume")->assertOk()->json();

        $this->assertCount(1, $resume['notes']);
        $this->assertEquals(15, $resume['notes'][0]['valeur']);
        $this->assertCount(0, $resume['bulletins'], 'Pas de bulletin tant que le conseil de classe n\'a pas eu lieu.');

        ConseilClasse::create(['classe_id' => $this->classe->id, 'trimestre' => 1, 'annee_scolaire' => '2025-2026', 'date_conseil' => '2026-01-10', 'pv' => 'PV', 'etablissement_id' => 1]);
        $resume = $this->getJson("/api/parent/enfants/{$this->enfant1->id}/resume")->assertOk()->json();
        $this->assertCount(1, $resume['bulletins']);
    }

    public function test_justification_validee_par_le_cpe_uniquement(): void
    {
        $absence = Absence::create(['etudiant_id' => $this->enfant1->id, 'date' => '2026-01-05', 'type' => 'absence', 'justifie' => false]);

        Sanctum::actingAs($this->parent);
        $demande = $this->postJson("/api/parent/enfants/{$this->enfant1->id}/absences/{$absence->id}/justifier", ['motif' => 'Maladie'])
            ->assertCreated()->json('demande');

        Sanctum::actingAs(User::factory()->create(['role' => 'secretaire', 'etablissement_id' => 1]));
        $this->postJson("/api/justifications/{$demande['id']}/decision", ['decision' => 'acceptee'])->assertForbidden();

        Sanctum::actingAs(User::factory()->create(['role' => 'cpe', 'etablissement_id' => 1]));
        $this->postJson("/api/justifications/{$demande['id']}/decision", ['decision' => 'acceptee'])->assertOk();
        $this->assertTrue((bool) $absence->fresh()->justifie);

        $this->postJson("/api/justifications/{$demande['id']}/decision", ['decision' => 'refusee'])->assertStatus(422);
    }

    public function test_parent_sans_plan_espace_parent_est_refuse(): void
    {
        $standard = Etablissement::create(['nom' => 'Standard', 'plan' => 'standard', 'statut_abonnement' => 'actif']);
        $parent = User::factory()->create(['role' => 'parent', 'etablissement_id' => $standard->id]);

        Sanctum::actingAs($parent);
        $this->getJson('/api/parent/enfants')->assertForbidden();
    }

    public function test_lien_parent_refuse_entre_etablissements(): void
    {
        $autre = Etablissement::create(['nom' => 'Autre', 'plan' => 'vip', 'statut_abonnement' => 'actif']);
        $parentAilleurs = User::factory()->create(['role' => 'parent', 'etablissement_id' => $autre->id]);

        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'etablissement_id' => 1]));
        // Le parent d'un autre établissement est introuvable pour cet administrateur (pas de fuite d'existence).
        $this->postJson("/api/etudiants/{$this->enfant1->id}/parents/{$parentAilleurs->id}")->assertNotFound();
    }

    public function test_parent_bloque_hors_de_son_espace(): void
    {
        Sanctum::actingAs($this->parent);

        $this->getJson("/api/dashboard")->assertForbidden();
        $this->getJson("/api/classes")->assertForbidden();
        $this->getJson("/api/etudiants")->assertForbidden();
        $this->getJson("/api/parent/enfants")->assertOk();
        $this->getJson("/api/me")->assertOk();
    }
}
