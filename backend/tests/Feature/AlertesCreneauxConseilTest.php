<?php

namespace Tests\Feature;

use App\Models\Alerte;
use App\Models\Classe;
use App\Models\ConseilClasse;
use App\Models\Creneau;
use App\Models\EmploiDuTemps;
use App\Models\Etudiant;
use App\Models\Matiere;
use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AlertesCreneauxConseilTest extends TestCase
{
    use RefreshDatabase;

    public function test_alerte_listee_puis_marquee_verifiee(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $eleve = Etudiant::factory()->create(['classe_id' => Classe::factory()->create()->id]);
        $alerte = Alerte::create([
            'etudiant_id' => $eleve->id,
            'type' => 'absence',
            'severity' => 'warning',
            'message' => '3 absences en 1 mois',
            'acknowledged' => false,
            'etablissement_id' => $admin->etablissement_id,
        ]);

        Sanctum::actingAs($admin);
        $this->getJson('/api/alertes')->assertOk()->assertJsonCount(1, 'data');

        $this->postJson("/api/alertes/{$alerte->id}/verifier")->assertOk();
        $this->assertTrue($alerte->fresh()->acknowledged);

        Sanctum::actingAs(User::factory()->create(['role' => 'enseignant']));
        $this->getJson('/api/alertes')->assertForbidden();
    }

    public function test_creneaux_crud_reserve_a_l_administration(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $creneau = $this->postJson('/api/creneaux', ['heure_debut' => '10:00', 'heure_fin' => '11:00', 'ordre' => 99])
            ->assertCreated()->json();

        $this->putJson("/api/creneaux/{$creneau['id']}", ['heure_debut' => '10:00', 'heure_fin' => '11:30', 'ordre' => 99])->assertOk();

        Sanctum::actingAs(User::factory()->create(['role' => 'enseignant']));
        $this->postJson('/api/creneaux', ['heure_debut' => '12:00', 'heure_fin' => '13:00', 'ordre' => 98])->assertForbidden();
        $this->getJson('/api/creneaux')->assertOk();
    }

    public function test_modification_de_frais_et_de_cours(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $frais = \App\Models\FraisScolaire::create([
            'etudiant_id' => Etudiant::factory()->create(['classe_id' => Classe::factory()->create()->id])->id,
            'etablissement_id' => $admin->etablissement_id,
            'libelle' => 'Scolarité',
            'annee_scolaire' => '2025-2026',
            'montant' => 50000,
        ]);
        $creneau = Creneau::create(['heure_debut' => '08:00', 'heure_fin' => '09:00', 'ordre' => 1]);
        $cours = EmploiDuTemps::create([
            'classe_id' => Classe::factory()->create()->id,
            'matiere_id' => Matiere::factory()->create()->id,
            'jour' => 2,
            'creneau_id' => $creneau->id,
            'salle' => 'S1',
        ]);

        Sanctum::actingAs($admin);
        $this->putJson("/api/frais-scolaires/{$frais->id}", ['montant_total' => 60000])->assertOk();
        $this->assertEquals(60000, (float) $frais->fresh()->montant);

        $this->putJson("/api/emploi-du-temps/{$cours->id}", [
            'classe_id' => $cours->classe_id,
            'matiere_id' => $cours->matiere_id,
            'jour' => 2,
            'creneau_id' => $creneau->id,
            'salle' => 'S9',
        ])->assertOk();
        $this->assertEquals('S9', $cours->fresh()->salle);
    }

    public function test_creneau_utilise_ne_peut_pas_etre_supprime(): void
    {
        $creneau = Creneau::create(['heure_debut' => '08:00', 'heure_fin' => '09:00', 'ordre' => 1]);
        EmploiDuTemps::create([
            'classe_id' => Classe::factory()->create()->id,
            'matiere_id' => Matiere::factory()->create()->id,
            'jour' => 1,
            'creneau_id' => $creneau->id,
        ]);

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->deleteJson("/api/creneaux/{$creneau->id}")->assertStatus(422);
    }

    public function test_conseil_de_classe_prepare_et_enregistre_le_pv(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $classe = Classe::factory()->create(['nom' => '6ème A']);
        $eleve = Etudiant::factory()->create(['classe_id' => $classe->id, 'nom' => 'Diop', 'prenom' => 'Awa']);
        Note::create(['etudiant_id' => $eleve->id, 'matiere_id' => Matiere::factory()->create()->id, 'trimestre' => 1, 'type' => 'devoir', 'valeur' => 15]);

        Sanctum::actingAs($admin);
        $this->getJson("/api/classes/{$classe->id}/conseil?trimestre=1")
            ->assertOk()
            ->assertJsonPath('eleves.0.decision', 'Admis(e)');

        $conseil = $this->postJson("/api/classes/{$classe->id}/conseils", [
            'trimestre' => 1,
            'date_conseil' => '2026-01-15',
            'observations' => 'Bon trimestre.',
        ])->assertCreated()->json('conseil');

        $this->assertStringContainsString('6ème A', ConseilClasse::find($conseil['id'])->pv);

        Sanctum::actingAs(User::factory()->create(['role' => 'enseignant']));
        $this->getJson("/api/classes/{$classe->id}/conseil?trimestre=1")->assertForbidden();
    }
}
