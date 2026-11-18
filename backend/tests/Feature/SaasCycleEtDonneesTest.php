<?php

namespace Tests\Feature;

use App\Mail\RelanceAbonnement;
use App\Models\Classe;
use App\Models\DemandeAbonnement;
use App\Models\Etablissement;
use App\Models\Etudiant;
use App\Models\Matiere;
use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Cycle de vie de l'abonnement (webhook, relances), permissions granulaires, import/export, comptabilité. */
class SaasCycleEtDonneesTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'secret-de-test';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.paiement.secret' => self::SECRET]);
    }

    private function etablissement(string $plan, array $attributs = []): Etablissement
    {
        return Etablissement::create(array_merge(
            ['nom' => "Lycée {$plan}", 'plan' => $plan, 'statut_abonnement' => 'actif', 'telephone' => '+235 66 00 00 00'],
            $attributs,
        ));
    }

    private function webhook(array $charge, ?string $signature = null)
    {
        $corps = json_encode($charge);
        $signature ??= hash_hmac('sha256', $corps, self::SECRET);

        return $this->call('POST', '/api/webhooks/paiement', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_SIGNATURE' => $signature,
        ], $corps);
    }

    public function test_webhook_refuse_une_signature_invalide(): void
    {
        $etab = $this->etablissement('standard', ['statut_abonnement' => 'suspendu']);

        $this->webhook([
            'reference' => 'PAY-1', 'etablissement_id' => $etab->id, 'statut' => 'reussi',
            'duree_mois' => 1, 'montant' => 25000, 'mode_paiement' => 'mobile_money',
        ], 'mauvaise-signature')->assertUnauthorized();

        $this->assertSame('suspendu', $etab->fresh()->statut_abonnement);
    }

    public function test_paiement_reussi_reactive_l_etablissement_une_seule_fois(): void
    {
        $etab = $this->etablissement('standard', ['statut_abonnement' => 'suspendu']);
        $demandeur = User::factory()->create(['role' => 'admin', 'etablissement_id' => $etab->id]);
        DemandeAbonnement::create([
            'etablissement_id' => $etab->id,
            'user_id' => $demandeur->id,
            'plan' => 'vip',
            'duree_mois' => 1,
            'montant' => 50000,
            'mode_paiement' => 'mobile_money',
            'reference' => 'PAY-2',
        ]);
        $charge = [
            'reference' => 'PAY-2', 'etablissement_id' => $etab->id, 'statut' => 'reussi', 'plan' => 'vip',
            'duree_mois' => 1, 'montant' => 50000, 'mode_paiement' => 'mobile_money',
        ];

        $this->webhook($charge)->assertOk()->assertJsonPath('statut', 'active');
        $etab->refresh();
        $this->assertTrue($etab->isActif());
        $this->assertSame('vip', $etab->plan);

        $this->webhook($charge)->assertOk()->assertJsonPath('statut', 'ignore');
        $this->assertDatabaseCount('abonnement_paiements', 1);
    }

    public function test_paiement_echoue_ne_suspend_pas_un_abonnement_actif(): void
    {
        $etab = $this->etablissement('vip');
        $demandeur = User::factory()->create(['role' => 'admin', 'etablissement_id' => $etab->id]);
        DemandeAbonnement::create([
            'etablissement_id' => $etab->id,
            'user_id' => $demandeur->id,
            'plan' => 'vip',
            'duree_mois' => 1,
            'montant' => 50000,
            'mode_paiement' => 'mobile_money',
            'reference' => 'PAY-3',
        ]);

        $this->webhook([
            'reference' => 'PAY-3', 'etablissement_id' => $etab->id, 'statut' => 'echoue',
        ])->assertOk()->assertJsonPath('statut', 'echoue');

        $this->assertSame('actif', $etab->fresh()->statut_abonnement);
        $this->assertTrue($etab->fresh()->isActif());
        $this->assertDatabaseHas('demandes_abonnement', ['reference' => 'PAY-3', 'statut' => 'refusee']);
    }

    public function test_webhook_refuse_un_montant_different_de_la_demande(): void
    {
        $etab = $this->etablissement('standard', ['statut_abonnement' => 'suspendu']);
        $demandeur = User::factory()->create(['role' => 'admin', 'etablissement_id' => $etab->id]);
        DemandeAbonnement::create([
            'etablissement_id' => $etab->id,
            'user_id' => $demandeur->id,
            'plan' => 'standard',
            'duree_mois' => 1,
            'montant' => 25000,
            'mode_paiement' => 'mobile_money',
            'reference' => 'PAY-4',
        ]);

        $this->webhook([
            'reference' => 'PAY-4', 'etablissement_id' => $etab->id, 'statut' => 'reussi', 'montant' => 50000,
        ])->assertUnprocessable();
        $this->assertSame('suspendu', $etab->fresh()->statut_abonnement);
        $this->assertDatabaseMissing('evenements_paiement', ['reference' => 'PAY-4']);
    }

    public function test_relance_a_sept_jours_envoyee_une_seule_fois_par_canal(): void
    {
        Mail::fake();
        $etab = $this->etablissement('standard', ['abonnement_fin' => today()->addDays(7)]);
        User::factory()->create(['role' => 'admin', 'etablissement_id' => $etab->id, 'email' => 'admin-relance@test.com']);

        $this->artisan('abonnements:relances')->assertSuccessful();
        $this->artisan('abonnements:relances')->assertSuccessful();

        Mail::assertQueued(RelanceAbonnement::class, 1);
        $this->assertDatabaseHas('relances_abonnement', ['etablissement_id' => $etab->id, 'canal' => 'email', 'envoye' => true]);
    }

    public function test_pas_de_relance_hors_des_echeances(): void
    {
        Mail::fake();
        $etab = $this->etablissement('standard', ['abonnement_fin' => today()->addDays(30)]);
        User::factory()->create(['role' => 'admin', 'etablissement_id' => $etab->id]);

        $this->artisan('abonnements:relances')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_le_bandeau_expose_les_jours_restants(): void
    {
        $etab = $this->etablissement('standard', ['abonnement_fin' => today()->addDays(3)]);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'etablissement_id' => $etab->id]));

        $this->getJson('/api/etablissement')->assertOk()->assertJsonPath('jours_restants', 3);
    }

    public function test_permission_notes_modifier_ouvre_la_modification_hors_affectation(): void
    {
        $etab = $this->etablissement('vip');
        $etudiant = Etudiant::factory()->create(['etablissement_id' => $etab->id]);
        $matiere = Matiere::factory()->create(['etablissement_id' => $etab->id]);
        $note = Note::factory()->create(['etudiant_id' => $etudiant->id, 'matiere_id' => $matiere->id, 'statut' => 'brouillon']);

        // Un secrétaire n'est affecté à aucune matière : seule la permission lui ouvre la modification.
        $secretaire = User::factory()->create(['role' => 'secretaire', 'etablissement_id' => $etab->id]);
        $this->assertFalse($secretaire->fresh()->can('update', $note), 'Sans permission, pas de modification.');

        $permission = \App\Models\Permission::where('code', 'notes.modifier')->first();
        $secretaire->permissions()->attach($permission->id);
        $this->assertTrue($secretaire->fresh()->can('update', $note), 'Avec la permission, la modification est autorisée.');
    }

    public function test_import_csv_apercu_puis_import_avec_detection_des_colonnes(): void
    {
        $etab = $this->etablissement('entreprise');
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'etablissement_id' => $etab->id]));

        $csv = "Nom;Prénom;Classe;Date de naissance;Sexe\n"
            ."Diallo;Mariam;Terminale A;12/03/2008;F\n"
            ."Sow;Ibrahim;Terminale A;2008-09-01;M\n"
            .";Sans nom;Terminale B;;M\n";
        $fichier = UploadedFile::fake()->createWithContent('eleves.csv', $csv);

        $apercu = $this->post('/api/import/etudiants', ['fichier' => $fichier, 'mode' => 'apercu'])
            ->assertOk()
            ->json();

        $this->assertSame(3, $apercu['total']);
        $this->assertSame(2, $apercu['valides']);
        $this->assertCount(1, $apercu['erreurs']);
        $this->assertDatabaseMissing('etudiants', ['nom' => 'Diallo']);

        $import = $this->post('/api/import/etudiants', [
            'fichier' => UploadedFile::fake()->createWithContent('eleves.csv', $csv), 'mode' => 'importer',
        ])->assertCreated()->json();

        $this->assertSame(2, $import['importes']);
        $this->assertDatabaseHas('etudiants', ['nom' => 'Diallo', 'prenom' => 'Mariam', 'etablissement_id' => $etab->id]);
        $this->assertSame(1, Classe::where('nom', 'Terminale A')->where('etablissement_id', $etab->id)->count());
    }

    public function test_import_refuse_sans_la_permission(): void
    {
        $etab = $this->etablissement('entreprise');
        Sanctum::actingAs(User::factory()->create(['role' => 'gestionnaire', 'etablissement_id' => $etab->id]));

        $this->post('/api/import/etudiants', [
            'fichier' => UploadedFile::fake()->createWithContent('e.csv', "Nom;Prénom\nA;B\n"), 'mode' => 'apercu',
        ])->assertForbidden()->assertJsonPath('code', 'permission_refusee');
    }

    public function test_export_etudiants_en_csv_filtre_par_etablissement(): void
    {
        $mien = $this->etablissement('entreprise');
        $autre = $this->etablissement('entreprise');
        Etudiant::factory()->create(['etablissement_id' => $mien->id, 'nom' => 'MonEleve']);
        Etudiant::factory()->create(['etablissement_id' => $autre->id, 'nom' => 'AutreEleve']);

        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'etablissement_id' => $mien->id]));
        $contenu = $this->get('/api/export/etudiants?format=csv')->assertOk()->streamedContent();

        $this->assertStringContainsString('MonEleve', $contenu);
        $this->assertStringNotContainsString('AutreEleve', $contenu);
    }

    public function test_synthese_comptable_reservee_a_entreprise(): void
    {
        $vip = $this->etablissement('vip');
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'etablissement_id' => $vip->id]));
        $this->getJson('/api/comptabilite/synthese')->assertForbidden()->assertJsonPath('code', 'plan_insuffisant');

        $entreprise = $this->etablissement('entreprise');
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'etablissement_id' => $entreprise->id]));
        $this->getJson('/api/comptabilite/synthese')
            ->assertOk()
            ->assertJsonStructure(['kpis' => ['facture', 'encaisse', 'impaye', 'taux_recouvrement'], 'encaissements_par_mois', 'par_classe']);
    }
}
