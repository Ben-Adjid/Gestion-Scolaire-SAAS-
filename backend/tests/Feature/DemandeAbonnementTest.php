<?php

namespace Tests\Feature;

use App\Models\DemandeAbonnement;
use App\Models\Etablissement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DemandeAbonnementTest extends TestCase
{
    use RefreshDatabase;

    private function etablissement(string $statut = 'actif'): Etablissement
    {
        return Etablissement::create([
            'nom' => 'École test',
            'plan' => 'standard',
            'statut_abonnement' => $statut,
        ]);
    }

    public function test_un_admin_peut_soumettre_un_paiement_mobile_et_un_superadmin_le_valider(): void
    {
        Storage::fake('private');
        config(['paiements.mobile_money.numero' => '+221770000000', 'paiements.mobile_money.titulaire' => 'École SaaS']);
        $etablissement = $this->etablissement('suspendu');
        $admin = User::factory()->create(['role' => 'admin', 'etablissement_id' => $etablissement->id]);
        Sanctum::actingAs($admin);

        $demande = $this->post('/api/abonnement/demandes', [
            'plan' => 'vip',
            'duree_mois' => 2,
            'mode_paiement' => 'mobile_money',
            'reference' => 'MM-001',
            'justificatif' => UploadedFile::fake()->createWithContent('recu.pdf', '%PDF-1.4 justificatif'),
        ])->assertCreated()
            ->assertJsonPath('demande.montant', 100000)
            ->assertJsonPath('demande.statut', 'en_attente');

        $this->assertDatabaseHas('demandes_abonnement', [
            'etablissement_id' => $etablissement->id,
            'user_id' => $admin->id,
            'reference' => 'MM-001',
            'montant' => 100000,
        ]);
        $dossier = DemandeAbonnement::where('reference', 'MM-001')->firstOrFail();
        Storage::disk('private')->assertExists($dossier->justificatif_path);
        $this->getJson('/api/abonnement/options')->assertOk()
            ->assertJsonPath('methodes.0.details.numero', '+221770000000')
            ->assertJsonPath('paiement_en_ligne.disponible', false);
        $this->postJson('/api/abonnement/demandes', [
            'plan' => 'standard', 'duree_mois' => 1, 'mode_paiement' => 'mobile_money', 'reference' => 'MM-002',
        ])->assertStatus(409);

        Sanctum::actingAs(User::factory()->create(['role' => 'super_admin', 'etablissement_id' => null]));
        $this->getJson('/api/super-admin/demandes-abonnement')->assertOk()->assertJsonCount(1);
        $this->get("/api/super-admin/demandes-abonnement/{$dossier->id}/justificatif")->assertOk();
        $this->postJson("/api/super-admin/demandes-abonnement/{$dossier->id}/approuver")
            ->assertOk()
            ->assertJsonPath('message', 'Paiement vérifié et abonnement activé.');

        $etablissement->refresh();
        $this->assertSame('vip', $etablissement->plan);
        $this->assertTrue($etablissement->isActif());
        $this->assertDatabaseHas('demandes_abonnement', ['id' => $dossier->id, 'statut' => 'approuvee']);
        $this->assertDatabaseHas('abonnement_paiements', ['reference' => 'MM-001', 'montant' => 100000]);
    }

    public function test_un_mode_non_configure_et_un_deuxieme_paiement_en_attente_sont_refuses(): void
    {
        config(['paiements.mobile_money.numero' => null]);
        $etablissement = $this->etablissement();
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'etablissement_id' => $etablissement->id]));

        $this->postJson('/api/abonnement/demandes', [
            'plan' => 'standard', 'duree_mois' => 1, 'mode_paiement' => 'mobile_money', 'reference' => 'MM-003',
        ])->assertUnprocessable()->assertJsonPath('code', 'mode_paiement_non_configure');
    }

    public function test_un_virement_affiche_les_coordonnees_bancaires_configurees(): void
    {
        config([
            'paiements.virement.banque' => 'Banque test',
            'paiements.virement.titulaire' => 'Gestion Scolaire',
            'paiements.virement.compte' => 'SN001234',
        ]);
        $etablissement = $this->etablissement();
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'etablissement_id' => $etablissement->id]));

        $this->getJson('/api/abonnement/options')->assertOk()
            ->assertJsonPath('methodes.1.disponible', true)
            ->assertJsonPath('methodes.1.details.banque', 'Banque test')
            ->assertJsonPath('methodes.1.details.compte', 'SN001234');

        $this->postJson('/api/abonnement/demandes', [
            'plan' => 'standard',
            'duree_mois' => 1,
            'mode_paiement' => 'virement',
            'reference' => 'BANK-002',
        ])->assertCreated()->assertJsonPath('demande.montant', 25000);
    }

    public function test_un_non_admin_ne_peut_pas_creer_ou_traiter_une_demande(): void
    {
        $etablissement = $this->etablissement();
        $enseignant = User::factory()->create(['role' => 'enseignant', 'etablissement_id' => $etablissement->id]);
        Sanctum::actingAs($enseignant);

        $this->getJson('/api/abonnement/options')->assertForbidden();

        $superadmin = User::factory()->create(['role' => 'super_admin', 'etablissement_id' => null]);
        $demandeur = User::factory()->create(['role' => 'admin', 'etablissement_id' => $etablissement->id]);
        $demande = DemandeAbonnement::create([
            'etablissement_id' => $etablissement->id,
            'user_id' => $demandeur->id,
            'plan' => 'vip',
            'duree_mois' => 1,
            'montant' => 50000,
            'mode_paiement' => 'virement',
            'reference' => 'BANK-001',
        ]);
        $this->postJson("/api/super-admin/demandes-abonnement/{$demande->id}/approuver")->assertForbidden();

        Sanctum::actingAs($superadmin);
        $this->postJson("/api/super-admin/demandes-abonnement/{$demande->id}/refuser", ['motif_refus' => 'Référence invalide'])
            ->assertOk();
        $this->assertDatabaseHas('demandes_abonnement', ['id' => $demande->id, 'statut' => 'refusee']);
    }
}
