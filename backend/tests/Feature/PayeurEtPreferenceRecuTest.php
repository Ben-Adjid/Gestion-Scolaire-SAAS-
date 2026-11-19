<?php

namespace Tests\Feature;

use App\Mail\PaymentReceiptMail;
use App\Models\Classe;
use App\Models\Etablissement;
use App\Models\Etudiant;
use App\Models\FraisScolaire;
use App\Models\Paiement;
use App\Models\User;
use App\Services\RecuPaiement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Payeur du reçu (saisi ou tuteur par défaut) et préférence du parent pour l'envoi par e-mail. */
class PayeurEtPreferenceRecuTest extends TestCase
{
    use RefreshDatabase;

    private Etablissement $etab;
    private Etudiant $eleve;
    private FraisScolaire $frais;

    protected function setUp(): void
    {
        parent::setUp();
        $this->etab = Etablissement::create(['nom' => 'École Payeur', 'plan' => 'vip', 'statut_abonnement' => 'actif', 'annee_scolaire' => '2026-2027']);
        $classe = Classe::create(['nom' => 'Seconde A', 'niveau' => 'Seconde', 'etablissement_id' => $this->etab->id]);
        $this->eleve = Etudiant::create(['nom' => 'Hassan', 'prenom' => 'Rania', 'genre' => 'F', 'date_naissance' => '2009-05-05',
            'classe_id' => $classe->id, 'etablissement_id' => $this->etab->id]);
        $tuteur = User::factory()->create(['role' => 'parent', 'name' => 'Ali Hassan', 'etablissement_id' => $this->etab->id, 'email' => 'ali.hassan@exemple.test']);
        $this->eleve->parents()->attach($tuteur->id, ['lien' => 'pere']);
        $this->frais = FraisScolaire::create(['etudiant_id' => $this->eleve->id, 'libelle' => 'Scolarité', 'montant' => 100000,
            'annee_scolaire' => '2026-2027', 'statut' => 'en_attente', 'etablissement_id' => $this->etab->id]);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'etablissement_id' => $this->etab->id]));
    }

    private function encaisser(array $donnees = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson("/api/frais-scolaires/{$this->frais->id}/paiements",
            $donnees + ['montant' => 40000, 'date_paiement' => now()->toDateString(), 'mode' => 'especes'], ['Accept' => 'application/json']);
    }

    public function test_le_payeur_saisi_est_enregistre_et_affiche(): void
    {
        $this->encaisser(['payeur_nom' => '  Mme Fatimé Ousmane  '])->assertCreated();

        $paiement = Paiement::withoutGlobalScopes()->latest('id')->firstOrFail();
        $this->assertSame('Mme Fatimé Ousmane', $paiement->payeur_nom);
        $this->assertSame('Mme Fatimé Ousmane', $paiement->payeur_affiche);
        $this->assertSame('Mme Fatimé Ousmane', RecuPaiement::donnees($paiement)['payeur']);
    }

    public function test_sans_payeur_saisi_le_nom_du_tuteur_est_repris(): void
    {
        $this->encaisser(['payeur_nom' => ''])->assertCreated();

        $paiement = Paiement::withoutGlobalScopes()->latest('id')->firstOrFail();
        $this->assertNull($paiement->payeur_nom);
        $this->assertSame('Ali Hassan', $paiement->payeur_affiche);
        $this->assertSame('Ali Hassan', RecuPaiement::donnees($paiement)['payeur']);
    }

    public function test_le_parent_peut_desactiver_le_recu_par_e_mail(): void
    {
        Mail::fake();
        $tuteur = $this->eleve->parents()->firstOrFail();
        Sanctum::actingAs($tuteur);

        $this->putJson('/api/parent/recu-email', ['recevoir' => false])
            ->assertOk()->assertJsonPath('recevoir_recu_email', false);
        $this->assertFalse($tuteur->fresh()->recevoir_recu_email);

        // Un encaissement ultérieur ne déclenche plus d'envoi pour ce tuteur.
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'etablissement_id' => $this->etab->id]));
        $this->encaisser()->assertCreated();
        Mail::assertNothingQueued();
    }

    public function test_un_non_parent_ne_peut_pas_changer_cette_preference(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'etablissement_id' => $this->etab->id]));

        $this->putJson('/api/parent/recu-email', ['recevoir' => false])->assertForbidden();
    }

    public function test_le_pdf_du_recu_est_toujours_genere_avec_le_payeur(): void
    {
        $this->encaisser(['payeur_nom' => 'Ousmane Fatimé'])->assertCreated();
        $paiement = Paiement::withoutGlobalScopes()->latest('id')->firstOrFail();

        $this->assertStringStartsWith('%PDF', RecuPaiement::generer($paiement));
        $this->assertSame(PaymentReceiptMail::class, get_class(new PaymentReceiptMail($paiement)));
    }
}
