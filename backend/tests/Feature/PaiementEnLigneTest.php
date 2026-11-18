<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\Etablissement;
use App\Models\Etudiant;
use App\Models\FraisScolaire;
use App\Models\Paiement;
use App\Models\TransactionEnLigne;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaiementEnLigneTest extends TestCase
{
    use RefreshDatabase;

    private Etablissement $etab;
    private User $parent;
    private FraisScolaire $frais;

    protected function setUp(): void
    {
        parent::setUp();
        $this->etab = Etablissement::create(['nom' => 'École Paie', 'plan' => 'vip', 'statut_abonnement' => 'actif', 'pays' => 'SN']);
        $classe = Classe::create(['nom' => '5e', 'niveau' => '5ème', 'etablissement_id' => $this->etab->id]);
        $eleve = Etudiant::factory()->create(['etablissement_id' => $this->etab->id, 'classe_id' => $classe->id]);
        $this->frais = FraisScolaire::create(['etudiant_id' => $eleve->id, 'annee_scolaire' => '2026-2027', 'libelle' => 'Scolarité', 'montant' => 150000, 'etablissement_id' => $this->etab->id]);
        $this->parent = User::factory()->create(['role' => 'parent', 'etablissement_id' => $this->etab->id]);
        $this->parent->enfants()->attach($eleve->id, ['lien' => 'mere']);
    }

    private function configurerCinetPay(): void
    {
        $this->etab->forceFill(['paiement_prestataire' => 'cinetpay', 'paiement_identifiants' => ['api_key' => 'cle-secrete-1234', 'site_id' => '445566']])->save();
    }

    public function test_simulation_complete_le_paiement_et_met_a_jour_le_reste(): void
    {
        Sanctum::actingAs($this->parent);

        $ref = $this->postJson("/api/parent/frais/{$this->frais->id}/payer", ['montant' => 50000])
            ->assertCreated()->assertJsonPath('prestataire', 'simulation')->assertJsonPath('devise', 'XOF')->json('reference');

        $this->postJson("/api/parent/paiements-en-ligne/{$ref}/simuler", ['issue' => 'reussi'])->assertOk()->assertJsonPath('statut', 'reussi');
        // Rejouer la confirmation ne crée pas un second paiement.
        $this->postJson("/api/parent/paiements-en-ligne/{$ref}/simuler", ['issue' => 'reussi'])->assertOk();

        $this->assertSame(1, Paiement::count());
        $this->assertSame(100000.0, $this->frais->fresh()->reste);
    }

    public function test_montants_refuses(): void
    {
        Sanctum::actingAs($this->parent);
        $this->postJson("/api/parent/frais/{$this->frais->id}/payer", ['montant' => 200000])->assertStatus(422);
        $this->postJson("/api/parent/frais/{$this->frais->id}/payer", ['montant' => 10002])->assertStatus(422);
        $this->postJson("/api/parent/frais/{$this->frais->id}/payer", ['montant' => 50])->assertStatus(422);
    }

    public function test_un_parent_ne_paie_pas_les_frais_d_un_autre_eleve(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'parent', 'etablissement_id' => $this->etab->id]));
        $this->postJson("/api/parent/frais/{$this->frais->id}/payer", ['montant' => 1000])->assertForbidden();
    }

    public function test_cinetpay_initie_puis_la_notification_est_verifiee_aupres_de_l_api(): void
    {
        $this->configurerCinetPay();
        Http::fake([
            'api-checkout.cinetpay.com/v2/payment/check' => Http::response(['code' => '00', 'data' => ['status' => 'ACCEPTED', 'amount' => '25000', 'payment_method' => 'OM']]),
            'api-checkout.cinetpay.com/v2/payment' => Http::response(['code' => '201', 'data' => ['payment_url' => 'https://checkout.cinetpay.com/payment/abc']]),
        ]);
        Sanctum::actingAs($this->parent);

        $ref = $this->postJson("/api/parent/frais/{$this->frais->id}/payer", ['montant' => 25000])
            ->assertCreated()->assertJsonPath('url_paiement', 'https://checkout.cinetpay.com/payment/abc')->json('reference');

        Http::assertSent(fn ($r) => $r->url() === 'https://api-checkout.cinetpay.com/v2/payment' && $r['apikey'] === 'cle-secrete-1234' && $r['currency'] === 'XOF');

        // La notification ne contient que la référence : le statut vient de l'API de vérification.
        $this->postJson('/api/paiement-en-ligne/notification/cinetpay', ['cpm_trans_id' => $ref, 'cpm_result' => '00'])->assertOk();
        $this->assertSame('reussi', TransactionEnLigne::where('reference', $ref)->value('statut'));
        $this->assertSame(1, Paiement::where('reference', $ref)->count());

        // Simuler n'est pas possible pour une transaction réelle.
        $this->postJson("/api/parent/paiements-en-ligne/{$ref}/simuler", ['issue' => 'reussi'])->assertForbidden();
    }

    public function test_notification_forgee_ou_montant_different_n_enregistre_rien(): void
    {
        $this->configurerCinetPay();
        Http::fake([
            'api-checkout.cinetpay.com/v2/payment/check' => Http::response(['code' => '00', 'data' => ['status' => 'ACCEPTED', 'amount' => '100']]),
            'api-checkout.cinetpay.com/v2/payment' => Http::response(['code' => '201', 'data' => ['payment_url' => 'https://checkout.cinetpay.com/payment/x']]),
        ]);
        Sanctum::actingAs($this->parent);
        $ref = $this->postJson("/api/parent/frais/{$this->frais->id}/payer", ['montant' => 25000])->json('reference');

        $this->postJson('/api/paiement-en-ligne/notification/cinetpay', ['cpm_trans_id' => $ref])->assertOk();
        $this->postJson('/api/paiement-en-ligne/notification/cinetpay', ['cpm_trans_id' => 'INCONNUE'])->assertOk();

        $this->assertSame('en_attente', TransactionEnLigne::where('reference', $ref)->value('statut'));
        $this->assertSame(0, Paiement::count());
    }

    public function test_configuration_par_l_admin_sans_jamais_renvoyer_la_cle(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'etablissement_id' => $this->etab->id]));

        $this->putJson('/api/etablissement/paiement-en-ligne', ['prestataire' => 'cinetpay', 'site_id' => '445566', 'api_key' => 'cle-secrete-1234'])
            ->assertOk()->assertJsonPath('api_key_masquee', '••••1234')->assertJsonPath('actif', 'cinetpay')
            ->assertJsonMissing(['api_key' => 'cle-secrete-1234']);
        $this->getJson('/api/etablissement')->assertOk()->assertJsonMissingPath('paiement_identifiants');
        $this->assertStringNotContainsString('cle-secrete', (string) \DB::table('etablissements')->where('id', $this->etab->id)->value('paiement_identifiants'));

        Sanctum::actingAs(User::factory()->create(['role' => 'secretaire', 'etablissement_id' => $this->etab->id]));
        $this->getJson('/api/etablissement/paiement-en-ligne')->assertForbidden();
    }

    public function test_formule_standard_sans_paiement_en_ligne(): void
    {
        $this->etab->update(['plan' => 'standard']);
        Sanctum::actingAs($this->parent);
        $this->postJson("/api/parent/frais/{$this->frais->id}/payer", ['montant' => 1000])->assertForbidden();
    }
}
