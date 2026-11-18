<?php

namespace Tests\Feature;

use App\Mail\RelanceAbonnement;
use App\Models\AbonnementPaiement;
use App\Models\Etablissement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Relance, réinitialisation du mot de passe administrateur, reçu d'abonnement, fiche école. */
class FonctionsSuperAdminTest extends TestCase
{
    use RefreshDatabase;

    private Etablissement $ecole;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(['role' => 'super_admin', 'etablissement_id' => null]));
        $this->ecole = Etablissement::create([
            'nom' => 'École Fonctions', 'plan' => 'vip', 'statut_abonnement' => 'actif',
            'abonnement_fin' => now()->addDays(5),
        ]);
        User::factory()->create(['role' => 'admin', 'etablissement_id' => $this->ecole->id, 'email' => 'chef@ecole-fonctions.test']);
    }

    public function test_relance_envoie_un_mail_a_chaque_administrateur(): void
    {
        Mail::fake();

        $this->postJson("/api/super-admin/etablissements/{$this->ecole->id}/relancer")
            ->assertOk()
            ->assertJsonPath('envoyes', 1);

        Mail::assertSent(RelanceAbonnement::class, fn ($mail) => $mail->hasTo('chef@ecole-fonctions.test'));
        $this->assertDatabaseHas('audits', ['action' => 'relance_abonnement']);
    }

    public function test_reinitialisation_change_le_mot_de_passe_et_deconnecte_l_admin(): void
    {
        $admin = User::withoutGlobalScopes()->where('email', 'chef@ecole-fonctions.test')->first();
        $admin->createToken('session')->plainTextToken;
        $ancien = $admin->password;

        $res = $this->postJson("/api/super-admin/etablissements/{$this->ecole->id}/reinitialiser-admin")->assertOk();

        $temporaire = $res->json('mot_de_passe_temporaire');
        $this->assertSame(12, strlen($temporaire));
        $admin->refresh();
        $this->assertNotSame($ancien, $admin->password);
        $this->assertTrue(Hash::check($temporaire, $admin->password));
        $this->assertSame(0, $admin->tokens()->count());
    }

    public function test_recu_d_abonnement_contient_le_montant_et_la_periode(): void
    {
        $paiement = AbonnementPaiement::create([
            'etablissement_id' => $this->ecole->id, 'plan' => 'vip', 'montant' => 150000, 'mode_paiement' => 'virement',
            'periode_debut' => '2026-11-05', 'periode_fin' => '2027-02-05',
        ]);

        $this->get("/api/super-admin/paiements-abonnement/{$paiement->id}/recu")
            ->assertOk()
            ->assertSee('150 000 FCFA', false)
            ->assertSee('École Fonctions', false);
    }

    public function test_fiche_etablissement_liste_ses_administrateurs(): void
    {
        $this->getJson("/api/super-admin/etablissements/{$this->ecole->id}")
            ->assertOk()
            ->assertJsonPath('administrateurs.0.email', 'chef@ecole-fonctions.test');
    }
}
