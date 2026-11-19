<?php

namespace Tests\Feature;

use App\Mail\PaymentReceiptMail;
use App\Models\Classe;
use App\Models\Etablissement;
use App\Models\Etudiant;
use App\Models\FraisScolaire;
use App\Models\User;
use App\Services\RecuPaiement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Reçu envoyé par e-mail au tuteur à chaque encaissement, avec le PDF en pièce jointe. */
class RecuEmailTest extends TestCase
{
    use RefreshDatabase;

    private Etablissement $etab;
    private Classe $classe;

    protected function setUp(): void
    {
        parent::setUp();
        $this->etab = Etablissement::create(['nom' => 'École Reçu', 'plan' => 'vip', 'statut_abonnement' => 'actif', 'annee_scolaire' => '2026-2027', 'couleur_principale' => '#1F5C4A']);
        $this->classe = Classe::create(['nom' => 'Terminale A', 'niveau' => 'Terminale', 'etablissement_id' => $this->etab->id]);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'etablissement_id' => $this->etab->id]));
    }

    private function eleveAvecFrais(?string $emailTuteur): array
    {
        $eleve = Etudiant::create(['nom' => 'Mahamat', 'prenom' => 'Idriss', 'genre' => 'M', 'date_naissance' => '2008-03-02',
            'classe_id' => $this->classe->id, 'etablissement_id' => $this->etab->id]);
        if ($emailTuteur !== null) {
            $tuteur = User::factory()->create(['role' => 'parent', 'etablissement_id' => $this->etab->id, 'email' => $emailTuteur]);
            $eleve->parents()->attach($tuteur->id, ['lien' => 'pere']);
        }
        $frais = FraisScolaire::create(['etudiant_id' => $eleve->id, 'libelle' => 'Scolarité', 'montant' => 100000,
            'annee_scolaire' => '2026-2027', 'statut' => 'en_attente', 'etablissement_id' => $this->etab->id]);

        return [$eleve, $frais];
    }

    private function encaisser(FraisScolaire $frais, int $montant = 40000): \Illuminate\Testing\TestResponse
    {
        return $this->postJson("/api/frais-scolaires/{$frais->id}/paiements", [
            'montant' => $montant, 'date_paiement' => now()->toDateString(), 'mode' => 'especes', 'reference' => 'REF-TEST',
        ], ['Accept' => 'application/json']);
    }

    public function test_le_tuteur_recoit_le_recu_a_chaque_encaissement(): void
    {
        Mail::fake();
        [, $frais] = $this->eleveAvecFrais('tuteur.idriss@exemple.test');

        $this->encaisser($frais)->assertCreated();

        Mail::assertSent(PaymentReceiptMail::class, fn ($mail) => $mail->hasTo('tuteur.idriss@exemple.test'));
    }

    public function test_sans_adresse_du_tuteur_aucun_e_mail_n_est_envoye_et_l_encaissement_reussit(): void
    {
        Mail::fake();
        [, $frais] = $this->eleveAvecFrais(null);

        $this->encaisser($frais)->assertCreated();

        Mail::assertNothingSent();
    }

    public function test_le_pdf_du_recu_est_valide_et_leger(): void
    {
        [$eleve, $frais] = $this->eleveAvecFrais(null);
        $paiement = $frais->paiements()->create(['etudiant_id' => $eleve->id, 'montant' => 40000, 'date_paiement' => now()->toDateString(),
            'mode_paiement' => 'especes', 'statut' => 'completed', 'etablissement_id' => $this->etab->id, 'enregistre_par' => null]);

        $pdf = RecuPaiement::generer($paiement);

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertLessThan(200 * 1024, strlen($pdf), 'Le reçu doit peser moins de 200 Ko.');
        $donnees = RecuPaiement::donnees($paiement);
        $this->assertSame('REC-2026-'.str_pad((string) $paiement->id, 4, '0', STR_PAD_LEFT), $donnees['numero']);
        $this->assertSame('Terminale A • Lycée', $donnees['eleve']['classe']);
        $this->assertSame('60 000 FCFA', $donnees['frais']['reste']);
    }
}
