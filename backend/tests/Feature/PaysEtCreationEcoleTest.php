<?php

namespace Tests\Feature;

use App\Models\AbonnementPaiement;
use App\Models\Etablissement;
use App\Models\Etudiant;
use App\Models\FraisScolaire;
use App\Models\Paiement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Multi-pays (monnaie, en-tête) et création d'école par essai ou premier paiement. */
class PaysEtCreationEcoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_referentiel_des_pays_est_public(): void
    {
        $this->getJson('/api/pays')->assertOk()
            ->assertJsonFragment(['code' => 'SN', 'monnaie' => 'FCFA', 'indicatif' => '+221'])
            ->assertJsonFragment(['code' => 'GN', 'monnaie' => 'GNF']);
    }

    public function test_l_ecole_choisit_son_pays_et_ses_documents_suivent(): void
    {
        $etab = Etablissement::create(['nom' => 'École de Conakry', 'plan' => 'vip', 'statut_abonnement' => 'actif']);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'etablissement_id' => $etab->id]));

        $this->postJson('/api/etablissement', ['nom' => 'École de Conakry', 'annee_scolaire' => '2026-2027', 'pays' => 'GN'])->assertOk();
        $this->getJson('/api/etablissement')->assertOk()
            ->assertJsonPath('pays', 'GN')
            ->assertJsonPath('pays_info.republique', 'RÉPUBLIQUE DE GUINÉE')
            ->assertJsonPath('pays_info.monnaie', 'GNF');

        $this->postJson('/api/etablissement', ['nom' => 'École de Conakry', 'annee_scolaire' => '2026-2027', 'pays' => 'XX'])
            ->assertStatus(422)->assertJsonValidationErrors('pays');

        $eleve = Etudiant::factory()->create(['etablissement_id' => $etab->id]);
        $frais = FraisScolaire::create(['etudiant_id' => $eleve->id, 'annee_scolaire' => '2026-2027', 'libelle' => 'Scolarité', 'montant' => 500000, 'etablissement_id' => $etab->id]);
        $paiement = Paiement::create(['frais_scolaire_id' => $frais->id, 'etudiant_id' => $eleve->id, 'montant' => 100000, 'date_paiement' => now()->toDateString(), 'mode_paiement' => 'especes', 'statut' => 'completed', 'etablissement_id' => $etab->id]);

        $this->get("/api/paiements/{$paiement->id}/recu")->assertOk()->assertSee('100 000 GNF')->assertDontSee('FCFA');
    }

    public function test_creation_par_essai_par_defaut(): void
    {
        Mail::fake();
        Sanctum::actingAs(User::factory()->create(['role' => 'super_admin', 'etablissement_id' => null]));

        $this->postJson('/api/super-admin/etablissements', [
            'nom' => 'Lycée Dakar', 'plan' => 'vip', 'pays' => 'SN', 'jours_essai' => 14,
            'admin_name' => 'Chef', 'admin_email' => 'chef@dakar.test', 'admin_password' => 'secret123',
        ])->assertCreated();

        $etab = Etablissement::where('nom', 'Lycée Dakar')->firstOrFail();
        $this->assertSame('essai', $etab->statut_abonnement);
        $this->assertSame(now()->addDays(14)->toDateString(), $etab->abonnement_fin->toDateString());
        $this->assertSame('SN', $etab->pays);
        $this->assertSame('Un Peuple — Un But — Une Foi', $etab->devise);
        $this->assertSame(0, AbonnementPaiement::where('etablissement_id', $etab->id)->count());
    }

    public function test_creation_avec_premier_paiement_cree_la_periode_et_la_facture(): void
    {
        Mail::fake();
        Sanctum::actingAs(User::factory()->create(['role' => 'super_admin', 'etablissement_id' => null]));

        $this->postJson('/api/super-admin/etablissements', [
            'nom' => 'Collège Payé', 'plan' => 'standard', 'demarrage' => 'paiement',
            'duree_mois' => 3, 'montant' => 75000, 'mode_paiement' => 'mobile_money', 'reference' => 'MM-1',
            'admin_name' => 'Chef', 'admin_email' => 'chef@paye.test', 'admin_password' => 'secret123',
        ])->assertCreated()->assertJsonPath('etablissement.statut_abonnement', 'actif');

        $etab = Etablissement::where('nom', 'Collège Payé')->firstOrFail();
        $this->assertSame(today()->addMonths(3)->toDateString(), $etab->abonnement_fin->toDateString());
        $this->assertDatabaseHas('abonnement_paiements', ['etablissement_id' => $etab->id, 'montant' => 75000, 'reference' => 'MM-1']);

        $this->postJson('/api/super-admin/etablissements', [
            'nom' => 'Sans montant', 'plan' => 'standard', 'demarrage' => 'paiement',
            'admin_name' => 'Chef', 'admin_email' => 'chef2@paye.test', 'admin_password' => 'secret123',
        ])->assertStatus(422)->assertJsonValidationErrors(['duree_mois', 'montant', 'mode_paiement']);
    }

    public function test_la_fiche_d_une_ecole_rattachee_a_un_groupe_s_affiche(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'super_admin', 'etablissement_id' => null]));
        $groupe = \App\Models\GroupeScolaire::create(['nom' => 'Groupe Espoir']);
        $etab = Etablissement::create(['nom' => 'Site Nord', 'plan' => 'entreprise', 'statut_abonnement' => 'actif', 'groupe_scolaire_id' => $groupe->id]);

        $this->getJson("/api/super-admin/etablissements/{$etab->id}")->assertOk()->assertJsonPath('groupe', 'Groupe Espoir');
    }

    public function test_modifier_l_echeance_sans_paiement_exige_un_motif(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'super_admin', 'etablissement_id' => null]));
        $etab = Etablissement::create(['nom' => 'À corriger', 'plan' => 'standard', 'statut_abonnement' => 'actif', 'abonnement_fin' => '2027-01-01']);

        // Modifier seulement les informations : accepté, même si le formulaire renvoie les valeurs actuelles.
        $this->putJson("/api/super-admin/etablissements/{$etab->id}", ['nom' => 'Corrigé', 'plan' => 'standard', 'abonnement_fin' => '2027-01-01'])->assertOk();

        $this->putJson("/api/super-admin/etablissements/{$etab->id}", ['abonnement_fin' => '2028-01-01'])
            ->assertStatus(422)->assertJsonValidationErrors('motif_correction');
        $this->assertSame('2027-01-01', $etab->fresh()->abonnement_fin->toDateString());

        $this->putJson("/api/super-admin/etablissements/{$etab->id}", ['abonnement_fin' => '2028-01-01', 'motif_correction' => 'Date saisie par erreur'])->assertOk();
        $this->assertSame('2028-01-01', $etab->fresh()->abonnement_fin->toDateString());
        $this->assertDatabaseHas('audits', ['action' => 'modification_etablissement', 'module' => 'etablissements']);
    }
}
