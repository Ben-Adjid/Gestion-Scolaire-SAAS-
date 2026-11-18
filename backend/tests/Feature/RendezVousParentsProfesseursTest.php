<?php

namespace Tests\Feature;

use App\Mail\NotificationParent;
use App\Models\Classe;
use App\Models\Enseignant;
use App\Models\Etablissement;
use App\Models\Etudiant;
use App\Models\RendezVous;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RendezVousParentsProfesseursTest extends TestCase
{
    use RefreshDatabase;

    private Etablissement $etab;
    private Etudiant $enfant;
    private User $parent;
    private Enseignant $prof;
    private User $compteProf;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->etab = Etablissement::create(['nom' => 'École RDV', 'plan' => 'vip', 'statut_abonnement' => 'actif']);
        $classe = Classe::create(['nom' => '3e A', 'niveau' => '3ème', 'etablissement_id' => $this->etab->id]);
        $this->enfant = Etudiant::factory()->create(['etablissement_id' => $this->etab->id, 'classe_id' => $classe->id]);
        $this->parent = User::factory()->create(['role' => 'parent', 'email' => 'p@rdv.test', 'etablissement_id' => $this->etab->id]);
        $this->parent->enfants()->attach($this->enfant->id, ['lien' => 'pere']);
        $this->prof = Enseignant::create(['nom' => 'Ali', 'prenom' => 'Moussa', 'email' => 'ali@rdv.test', 'etablissement_id' => $this->etab->id]);
        $this->prof->classes()->attach($classe->id);
        $this->compteProf = User::factory()->create(['role' => 'enseignant', 'enseignant_id' => $this->prof->id, 'etablissement_id' => $this->etab->id]);
    }

    private function demander(array $surcharge = []): \Illuminate\Testing\TestResponse
    {
        Sanctum::actingAs($this->parent);

        return $this->postJson("/api/parent/enfants/{$this->enfant->id}/rendez-vous", array_merge([
            'enseignant_id' => $this->prof->id,
            'motif' => 'Faire le point sur les résultats',
            'creneaux' => [now()->addDays(2)->format('Y-m-d\T10:00'), now()->addDays(3)->format('Y-m-d\T15:30')],
        ], $surcharge));
    }

    public function test_le_parent_voit_les_enseignants_et_demande_un_rendez_vous(): void
    {
        Sanctum::actingAs($this->parent);
        $this->getJson("/api/parent/enfants/{$this->enfant->id}/enseignants")->assertOk()->assertJsonPath('0.nom', 'Moussa Ali');

        $this->demander()->assertCreated()->assertJsonPath('rendez_vous.statut', 'demande');
        $this->getJson('/api/parent/rendez-vous')->assertOk()->assertJsonCount(1);
    }

    public function test_demandes_invalides(): void
    {
        $autreProf = Enseignant::create(['nom' => 'Hors', 'prenom' => 'Classe', 'email' => 'hors@rdv.test', 'etablissement_id' => $this->etab->id]);
        $this->demander(['enseignant_id' => $autreProf->id])->assertStatus(422);
        $this->demander(['creneaux' => [now()->subDay()->format('Y-m-d\T10:00')]])->assertStatus(422);

        $autreEnfant = Etudiant::factory()->create(['etablissement_id' => $this->etab->id, 'classe_id' => $this->enfant->classe_id]);
        $this->postJson("/api/parent/enfants/{$autreEnfant->id}/rendez-vous", [
            'enseignant_id' => $this->prof->id, 'motif' => 'Test motif', 'creneaux' => [now()->addDay()->format('Y-m-d\T10:00')],
        ])->assertForbidden();
    }

    public function test_l_enseignant_confirme_et_le_parent_est_prevenu(): void
    {
        $id = $this->demander()->json('rendez_vous.id');

        Sanctum::actingAs($this->compteProf);
        $this->getJson('/api/rendez-vous')->assertOk()->assertJsonPath('data.0.id', $id);
        $this->postJson("/api/rendez-vous/{$id}/confirmer", [
            'date_retenue' => now()->addDays(2)->format('Y-m-d\T10:00'), 'lieu' => 'Salle des professeurs',
        ])->assertOk();

        $this->assertSame('confirme', RendezVous::find($id)->statut);
        Mail::assertQueued(NotificationParent::class, fn ($m) => $m->hasTo('p@rdv.test'));
        $this->postJson("/api/rendez-vous/{$id}/refuser", ['reponse' => 'Trop tard'])->assertStatus(422);
    }

    public function test_un_autre_enseignant_ne_peut_ni_voir_ni_traiter(): void
    {
        $id = $this->demander()->json('rendez_vous.id');
        $autre = Enseignant::create(['nom' => 'Autre', 'prenom' => 'Prof', 'email' => 'autre@rdv.test', 'etablissement_id' => $this->etab->id]);
        Sanctum::actingAs(User::factory()->create(['role' => 'enseignant', 'enseignant_id' => $autre->id, 'etablissement_id' => $this->etab->id]));

        $this->getJson('/api/rendez-vous')->assertOk()->assertJsonCount(0, 'data');
        $this->postJson("/api/rendez-vous/{$id}/refuser", ['reponse' => 'Non'])->assertForbidden();
    }

    public function test_refus_motive_par_la_direction_puis_annulation_impossible(): void
    {
        $id = $this->demander()->json('rendez_vous.id');
        Sanctum::actingAs(User::factory()->create(['role' => 'censeur', 'etablissement_id' => $this->etab->id]));

        $this->postJson("/api/rendez-vous/{$id}/refuser", [])->assertStatus(422);
        $this->postJson("/api/rendez-vous/{$id}/refuser", ['reponse' => 'L’enseignant est en formation ce mois-ci'])->assertOk();

        Sanctum::actingAs($this->parent);
        $this->postJson("/api/parent/rendez-vous/{$id}/annuler")->assertStatus(422);
    }
}
