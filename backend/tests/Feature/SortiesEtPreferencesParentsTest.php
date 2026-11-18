<?php

namespace Tests\Feature;

use App\Mail\NotificationParent;
use App\Models\Absence;
use App\Models\AutorisationSortie;
use App\Models\Classe;
use App\Models\Etablissement;
use App\Models\Etudiant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Notifications à la carte des parents et autorisations de sortie signées. */
class SortiesEtPreferencesParentsTest extends TestCase
{
    use RefreshDatabase;

    private Etablissement $etab;
    private User $parent;
    private Etudiant $enfant;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->etab = Etablissement::create(['nom' => 'École Sorties', 'plan' => 'vip', 'statut_abonnement' => 'actif']);
        $classe = Classe::factory()->create(['etablissement_id' => $this->etab->id]);
        $this->enfant = Etudiant::factory()->create(['etablissement_id' => $this->etab->id, 'classe_id' => $classe->id]);
        $this->parent = User::factory()->create([
            'role' => 'parent', 'name' => 'Achta Brahim', 'email' => 'achta@parent.test', 'etablissement_id' => $this->etab->id,
        ]);
        $this->parent->enfants()->attach($this->enfant->id, ['lien' => 'mere']);
    }

    private function signer(array $surcharge = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson("/api/parent/enfants/{$this->enfant->id}/sorties", array_merge([
            'date_sortie' => now()->toDateString(),
            'heure_sortie' => '15:30',
            'motif' => 'Rendez-vous médical',
            'accompagnant' => 'Son oncle Ali',
            'signature_nom' => 'brahim  ACHTA',
            'attestation' => true,
        ], $surcharge));
    }

    public function test_un_parent_signe_une_autorisation_avec_preuve(): void
    {
        Sanctum::actingAs($this->parent);

        $id = $this->signer()->assertCreated()->json('autorisation.id');
        $a = AutorisationSortie::find($id);

        $this->assertSame('en_attente', $a->statut);
        $this->assertSame(64, strlen($a->signature_empreinte));
        $this->assertNotNull($a->signature_ip);
        $this->assertTrue($a->empreinteValide());

        // Toute modification après signature casse l'empreinte.
        AutorisationSortie::whereKey($id)->update(['motif' => 'Autre motif']);
        $this->assertFalse(AutorisationSortie::find($id)->empreinteValide());
    }

    public function test_signature_refusee_sans_attestation_ou_avec_un_autre_nom(): void
    {
        Sanctum::actingAs($this->parent);

        $this->signer(['attestation' => false])->assertStatus(422)->assertJsonValidationErrors('attestation');
        $this->signer(['signature_nom' => 'Quelqu’un d’autre'])->assertStatus(422)->assertJsonValidationErrors('signature_nom');
        $this->signer(['date_sortie' => now()->subDay()->toDateString()])->assertStatus(422);
    }

    public function test_un_parent_ne_signe_pas_pour_l_enfant_d_un_autre(): void
    {
        $autre = Etudiant::factory()->create(['etablissement_id' => $this->etab->id, 'classe_id' => $this->enfant->classe_id]);
        Sanctum::actingAs($this->parent);

        $this->postJson("/api/parent/enfants/{$autre->id}/sorties", [
            'date_sortie' => now()->toDateString(), 'heure_sortie' => '15:30', 'motif' => 'Test',
            'signature_nom' => 'Achta Brahim', 'attestation' => true,
        ])->assertForbidden();
    }

    public function test_circuit_complet_validation_puis_sortie_et_notification(): void
    {
        Sanctum::actingAs($this->parent);
        $id = $this->signer()->json('autorisation.id');

        $surveillant = User::factory()->create(['role' => 'surveillant', 'etablissement_id' => $this->etab->id]);
        Sanctum::actingAs($surveillant);

        $this->postJson("/api/autorisations-sortie/{$id}/sortie")->assertStatus(422);
        $this->getJson('/api/autorisations-sortie?date='.now()->toDateString())
            ->assertOk()->assertJsonPath('data.0.signature_intacte', true);
        $this->postJson("/api/autorisations-sortie/{$id}/decision", ['decision' => 'validee'])->assertOk();
        $this->postJson("/api/autorisations-sortie/{$id}/sortie")->assertOk();

        $this->assertSame('effectuee', AutorisationSortie::find($id)->statut);
        Mail::assertQueued(NotificationParent::class, fn ($m) => $m->hasTo('achta@parent.test'));

        $this->get("/api/autorisations-sortie/{$id}/document")->assertOk()->assertSee('Achta Brahim')->assertSee('Empreinte SHA-256');
    }

    public function test_refus_exige_un_motif_et_un_enseignant_n_a_pas_acces(): void
    {
        Sanctum::actingAs($this->parent);
        $id = $this->signer()->json('autorisation.id');

        Sanctum::actingAs(User::factory()->create(['role' => 'enseignant', 'etablissement_id' => $this->etab->id]));
        $this->getJson('/api/autorisations-sortie')->assertForbidden();

        Sanctum::actingAs(User::factory()->create(['role' => 'cpe', 'etablissement_id' => $this->etab->id]));
        $this->postJson("/api/autorisations-sortie/{$id}/decision", ['decision' => 'refusee'])->assertStatus(422);
        $this->postJson("/api/autorisations-sortie/{$id}/decision", ['decision' => 'refusee', 'commentaire' => 'Pas joignable'])->assertOk();
    }

    public function test_un_autre_etablissement_ne_voit_pas_les_autorisations(): void
    {
        Sanctum::actingAs($this->parent);
        $id = $this->signer()->json('autorisation.id');

        $ailleurs = Etablissement::create(['nom' => 'Ailleurs', 'plan' => 'vip', 'statut_abonnement' => 'actif']);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'etablissement_id' => $ailleurs->id]));

        $this->getJson('/api/autorisations-sortie')->assertOk()->assertJsonCount(0, 'data');
        $this->postJson("/api/autorisations-sortie/{$id}/decision", ['decision' => 'validee'])->assertNotFound();
        $this->get("/api/autorisations-sortie/{$id}/document")->assertNotFound();
    }

    public function test_preferences_a_la_carte_coupent_le_canal_choisi(): void
    {
        Sanctum::actingAs($this->parent);
        $this->getJson('/api/parent/preferences-notifications')->assertOk()->assertJsonPath('preferences.absence.email', true);
        $this->putJson('/api/parent/preferences-notifications', ['preferences' => ['absence' => ['email' => false]]])
            ->assertOk()->assertJsonPath('preferences.absence.email', false)->assertJsonPath('preferences.retard.email', true);

        $absence = Absence::create(['etudiant_id' => $this->enfant->id, 'date' => now()->toDateString(), 'type' => 'absence', 'justifie' => false, 'etablissement_id' => $this->etab->id]);
        app(\App\Services\NotificationParentsService::class)->notifierAbsence($this->enfant, $absence);
        Mail::assertNothingQueued();

        $retard = Absence::create(['etudiant_id' => $this->enfant->id, 'date' => now()->toDateString(), 'type' => 'retard', 'justifie' => false, 'etablissement_id' => $this->etab->id]);
        app(\App\Services\NotificationParentsService::class)->notifierAbsence($this->enfant, $retard);
        Mail::assertQueued(NotificationParent::class, 1);
    }

    public function test_un_parent_peut_changer_son_mot_de_passe_provisoire(): void
    {
        $this->parent->forceFill(['doit_changer_mot_de_passe' => true, 'password' => bcrypt('provisoire1')])->save();
        Sanctum::actingAs($this->parent);

        $this->getJson('/api/parent/enfants')->assertStatus(403);
        $this->postJson('/api/mot-de-passe/changer', [
            'actuel' => 'provisoire1', 'nouveau' => 'NouveauSecret#2026', 'nouveau_confirmation' => 'NouveauSecret#2026',
        ])->assertOk();
    }
}
