<?php

namespace Tests\Feature;

use App\Mail\NotificationParent;
use App\Models\Classe;
use App\Models\Etablissement;
use App\Models\Etudiant;
use App\Models\FraisScolaire;
use App\Models\Paiement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConformiteRelancesTest extends TestCase
{
    use RefreshDatabase;

    public function test_relances_listent_les_restes_a_payer_les_plus_en_retard_d_abord(): void
    {
        $gestionnaire = User::factory()->create(['role' => 'gestionnaire']);
        $classe = Classe::factory()->create();
        $eleve = Etudiant::factory()->create(['classe_id' => $classe->id]);
        $soldeEleve = Etudiant::factory()->create(['classe_id' => $classe->id]);

        FraisScolaire::create(['etudiant_id' => $eleve->id, 'etablissement_id' => $gestionnaire->etablissement_id, 'libelle' => 'A', 'annee_scolaire' => '2025-2026', 'montant' => 50000, 'date_echeance' => now()->subDays(20)->toDateString()]);
        $solde = FraisScolaire::create(['etudiant_id' => $soldeEleve->id, 'etablissement_id' => $gestionnaire->etablissement_id, 'libelle' => 'B', 'annee_scolaire' => '2025-2026', 'montant' => 10000]);
        Paiement::create(['frais_scolaire_id' => $solde->id, 'etudiant_id' => $soldeEleve->id, 'etablissement_id' => $gestionnaire->etablissement_id, 'montant' => 10000, 'date_paiement' => now()->toDateString(), 'mode_paiement' => 'especes', 'statut' => 'completed']);

        Sanctum::actingAs($gestionnaire);
        $res = $this->getJson('/api/frais-scolaires/relances')->assertOk();

        $this->assertEquals(1, $res->json('total'));
        $this->assertEquals(50000, $res->json('montant_du'));
        $this->assertEquals($eleve->id, $res->json('relances.0.etudiant.id'));
        $this->assertGreaterThanOrEqual(20, $res->json('relances.0.jours_de_retard'));
    }

    public function test_export_rgpd_eleve_reserve_au_personnel_autorise(): void
    {
        $eleve = Etudiant::factory()->create(['classe_id' => Classe::factory()->create()->id]);

        Sanctum::actingAs(User::factory()->create(['role' => 'secretaire']));
        $this->getJson("/api/etudiants/{$eleve->id}/export-rgpd")
            ->assertOk()
            ->assertJsonPath('eleve.matricule', $eleve->matricule);

        Sanctum::actingAs(User::factory()->create(['role' => 'gestionnaire']));
        $this->getJson("/api/etudiants/{$eleve->id}/export-rgpd")->assertForbidden();

        Sanctum::actingAs(User::factory()->create(['role' => 'enseignant']));
        $this->getJson("/api/etudiants/{$eleve->id}/export-rgpd")->assertForbidden();
    }

    public function test_standard_peut_relancer_individuellement_un_impaye_une_fois_par_jour(): void
    {
        Mail::fake();
        $etablissement = Etablissement::create([
        'nom' => 'École Standard',
        'plan' => 'standard',
        'statut_abonnement' => 'actif',
        ]);
        $gestionnaire = User::factory()->create(['role' => 'gestionnaire', 'etablissement_id' => $etablissement->id]);
        $classe = Classe::factory()->create(['etablissement_id' => $etablissement->id]);
        $eleve = Etudiant::factory()->create(['etablissement_id' => $etablissement->id, 'classe_id' => $classe->id]);
        $frais = FraisScolaire::create([
        'etudiant_id' => $eleve->id,
        'etablissement_id' => $etablissement->id,
        'libelle' => 'Inscription',
        'annee_scolaire' => '2026-2027',
        'montant' => 25000,
        ]);
        $parent = User::factory()->create([
        'role' => 'parent',
        'etablissement_id' => $etablissement->id,
        'email' => 'parent-relance@test.com',
        ]);
        $parent->enfants()->attach($eleve->id, ['lien' => 'parent']);
        Sanctum::actingAs($gestionnaire);

        $this->postJson("/api/frais-scolaires/{$frais->id}/relancer")
        ->assertOk()
        ->assertJsonPath('envoyees', 1);
        $this->postJson("/api/frais-scolaires/{$frais->id}/relancer")
        ->assertOk()
        ->assertJsonPath('envoyees', 0)
        ->assertJsonPath('ignorees', 1);

        Mail::assertQueued(NotificationParent::class, 1);
        $this->assertDatabaseHas('notifications_envoyees', [
        'etablissement_id' => $etablissement->id,
        'destinataire_id' => $parent->id,
        'canal' => 'email',
        'statut' => 'envoyee',
        ]);
    }
}
