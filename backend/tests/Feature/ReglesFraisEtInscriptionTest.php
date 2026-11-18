<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\Etablissement;
use App\Models\Etudiant;
use App\Models\FraisScolaire;
use App\Models\Note;
use App\Models\Matiere;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Règles de gestion : passage en classe supérieure, frais (paiement, modification, suppression), messages clairs. */
class ReglesFraisEtInscriptionTest extends TestCase
{
    use RefreshDatabase;

    private Etablissement $etab;
    private Classe $troisieme;
    private Classe $seconde;

    protected function setUp(): void
    {
        parent::setUp();
        $this->etab = Etablissement::create(['nom' => 'École Règles', 'plan' => 'vip', 'statut_abonnement' => 'actif']);
        $this->troisieme = Classe::create(['nom' => '3e A', 'niveau' => '3ème', 'etablissement_id' => $this->etab->id]);
        $this->seconde = Classe::create(['nom' => '2nde A', 'niveau' => 'Seconde', 'etablissement_id' => $this->etab->id]);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'etablissement_id' => $this->etab->id]));
    }

    private function eleveAvecMoyenne(float $valeur): Etudiant
    {
        $eleve = Etudiant::factory()->create(['etablissement_id' => $this->etab->id, 'classe_id' => $this->troisieme->id]);
        $matiere = Matiere::create(['nom' => 'Maths', 'code' => 'M'.$eleve->id, 'coefficient' => 1, 'etablissement_id' => $this->etab->id]);
        Note::create(['etudiant_id' => $eleve->id, 'matiere_id' => $matiere->id, 'valeur' => $valeur, 'trimestre' => 1, 'type' => 'devoir', 'statut' => 'valide', 'etablissement_id' => $this->etab->id]);

        return $eleve;
    }

    private function reinscrire(Etudiant $eleve, Classe $classe): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/inscriptions', [
            'type' => 'ancien', 'etudiant_id' => $eleve->id, 'classe_id' => $classe->id,
            'annee_scolaire' => '2027-2028', 'date_inscription' => now()->toDateString(), 'statut' => 'active',
        ]);
    }

    public function test_un_eleve_non_admis_ne_monte_pas_de_niveau(): void
    {
        $eleve = $this->eleveAvecMoyenne(7);
        $this->reinscrire($eleve, $this->seconde)->assertStatus(422)
            ->assertJsonPath('errors.classe_id.0', fn ($m) => str_contains($m, 'moyenne annuelle est de 7/20'));
        $this->assertSame(0, \App\Models\Inscription::where('etudiant_id', $eleve->id)->count());
    }

    public function test_un_eleve_sans_note_ne_monte_pas_de_niveau(): void
    {
        $eleve = Etudiant::factory()->create(['etablissement_id' => $this->etab->id, 'classe_id' => $this->troisieme->id]);
        $this->reinscrire($eleve, $this->seconde)->assertStatus(422);
    }

    public function test_un_eleve_admis_monte_et_un_redoublement_reste_libre(): void
    {
        $admis = $this->eleveAvecMoyenne(12);
        $this->reinscrire($admis, $this->seconde)->assertCreated();

        $redouble = $this->eleveAvecMoyenne(5);
        $this->reinscrire($redouble, $this->troisieme)->assertCreated();
    }

    public function test_les_frais_soldes_n_acceptent_plus_de_paiement(): void
    {
        $eleve = Etudiant::factory()->create(['etablissement_id' => $this->etab->id, 'classe_id' => $this->troisieme->id]);
        $frais = FraisScolaire::create(['etudiant_id' => $eleve->id, 'annee_scolaire' => '2026-2027', 'libelle' => 'Scolarité', 'montant' => 10000, 'etablissement_id' => $this->etab->id]);

        $this->postJson("/api/frais-scolaires/{$frais->id}/paiements", ['montant' => 10000, 'date_paiement' => now()->toDateString(), 'mode' => 'especes'])->assertCreated();
        $this->postJson("/api/frais-scolaires/{$frais->id}/paiements", ['montant' => 100, 'date_paiement' => now()->toDateString(), 'mode' => 'cheque'])
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'soldés'));
    }

    public function test_seuls_especes_et_cheque_sont_acceptes(): void
    {
        $eleve = Etudiant::factory()->create(['etablissement_id' => $this->etab->id, 'classe_id' => $this->troisieme->id]);
        $frais = FraisScolaire::create(['etudiant_id' => $eleve->id, 'annee_scolaire' => '2026-2027', 'libelle' => 'Scolarité', 'montant' => 10000, 'etablissement_id' => $this->etab->id]);

        $this->postJson("/api/frais-scolaires/{$frais->id}/paiements", ['montant' => 1000, 'date_paiement' => now()->toDateString(), 'mode' => 'mobile_money'])
            ->assertStatus(422)->assertJsonValidationErrors('mode');
        $this->postJson("/api/frais-scolaires/{$frais->id}/paiements", ['montant' => 1000, 'date_paiement' => now()->toDateString(), 'mode' => 'cheque'])->assertCreated();
    }

    public function test_frais_payes_ne_peuvent_ni_etre_supprimes_ni_passer_sous_le_paye(): void
    {
        $eleve = Etudiant::factory()->create(['etablissement_id' => $this->etab->id, 'classe_id' => $this->troisieme->id]);
        $frais = FraisScolaire::create(['etudiant_id' => $eleve->id, 'annee_scolaire' => '2026-2027', 'libelle' => 'Scolarité', 'montant' => 10000, 'etablissement_id' => $this->etab->id]);
        $this->postJson("/api/frais-scolaires/{$frais->id}/paiements", ['montant' => 4000, 'date_paiement' => now()->toDateString(), 'mode' => 'especes'])->assertCreated();

        $this->putJson("/api/frais-scolaires/{$frais->id}", ['montant_total' => 3000])->assertStatus(422)->assertJsonValidationErrors('montant_total');
        $this->deleteJson("/api/frais-scolaires/{$frais->id}")->assertStatus(422);
        $this->assertNotNull(FraisScolaire::find($frais->id));

        $libre = FraisScolaire::create(['etudiant_id' => $eleve->id, 'annee_scolaire' => '2026-2027', 'libelle' => 'Test', 'montant' => 500, 'etablissement_id' => $this->etab->id]);
        $this->deleteJson("/api/frais-scolaires/{$libre->id}")->assertOk();
        $this->assertNull(FraisScolaire::find($libre->id));
    }

    public function test_recherche_inconnue_donne_un_message_clair_en_francais(): void
    {
        $this->getJson('/api/etudiants/lookup?matricule=INEXISTANT')->assertStatus(404)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'Aucun élève'));
        $this->getJson('/api/etudiants/999999')->assertStatus(404)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'introuvable'));
    }

    public function test_la_liste_des_frais_affiche_la_classe_de_l_eleve(): void
    {
        $eleve = Etudiant::factory()->create(['etablissement_id' => $this->etab->id, 'classe_id' => $this->troisieme->id]);
        FraisScolaire::create(['etudiant_id' => $eleve->id, 'annee_scolaire' => '2026-2027', 'libelle' => 'Scolarité', 'montant' => 10000, 'etablissement_id' => $this->etab->id]);

        $this->getJson('/api/frais-scolaires')->assertOk()->assertJsonPath('data.0.etudiant.classe.nom', '3e A');
    }

    public function test_le_bulletin_d_un_eleve_donne_sa_classe_et_son_niveau(): void
    {
        $eleve = $this->eleveAvecMoyenne(13);
        $this->getJson("/api/etudiants/{$eleve->id}/bulletin")->assertOk()
            ->assertJsonPath('classe.nom', '3e A')->assertJsonPath('classe.niveau', '3ème');
    }
}
