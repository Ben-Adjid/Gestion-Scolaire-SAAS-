<?php

namespace Tests\Feature;

use App\Mail\ReinitialisationMotDePasse;
use App\Models\Etablissement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Mot de passe oublié, réinitialisation par lien, changement obligatoire d'un mot de passe temporaire. */
class MotDePasseTest extends TestCase
{
    use RefreshDatabase;

    private User $compte;

    protected function setUp(): void
    {
        parent::setUp();
        $etab = Etablissement::create(['nom' => 'MDP', 'plan' => 'vip', 'statut_abonnement' => 'actif']);
        $this->compte = User::factory()->create([
            'role' => 'admin', 'etablissement_id' => $etab->id, 'email' => 'oubli@test.com', 'password' => Hash::make('ancien-mdp'),
        ]);
    }

    private function jetonDuMail(): string
    {
        $jeton = null;
        Mail::assertSent(ReinitialisationMotDePasse::class, function (ReinitialisationMotDePasse $mail) use (&$jeton) {
            preg_match('/token=([A-Za-z0-9]+)/', $mail->render(), $m);
            $jeton = $m[1] ?? null;

            return true;
        });

        return $jeton;
    }

    public function test_demande_envoie_un_lien_et_reinitialisation_change_le_mot_de_passe(): void
    {
        Mail::fake();

        $this->postJson('/api/mot-de-passe/oublie', ['email' => 'oubli@test.com'])->assertOk();
        $jeton = $this->jetonDuMail();
        $this->assertNotEmpty($jeton);

        $this->postJson('/api/mot-de-passe/reinitialiser', [
            'email' => 'oubli@test.com', 'token' => $jeton, 'password' => 'nouveau-mdp-1', 'password_confirmation' => 'nouveau-mdp-1',
        ])->assertOk();

        $this->postJson('/api/login', ['email' => 'oubli@test.com', 'password' => 'nouveau-mdp-1'])->assertOk();
        $this->postJson('/api/login', ['email' => 'oubli@test.com', 'password' => 'ancien-mdp'])->assertUnprocessable();
    }

    public function test_reponse_identique_pour_une_adresse_inconnue_sans_envoi(): void
    {
        Mail::fake();

        $this->postJson('/api/mot-de-passe/oublie', ['email' => 'inconnu@test.com'])
            ->assertOk()
            ->assertJsonPath('message', 'Si un compte existe pour cette adresse, un lien de réinitialisation vient d’être envoyé.');

        Mail::assertNothingSent();
    }

    public function test_lien_expire_est_refuse(): void
    {
        Mail::fake();
        $this->postJson('/api/mot-de-passe/oublie', ['email' => 'oubli@test.com']);
        $jeton = $this->jetonDuMail();
        DB::table('password_reset_tokens')->where('email', 'oubli@test.com')->update(['created_at' => now()->subMinutes(61)]);

        $this->postJson('/api/mot-de-passe/reinitialiser', [
            'email' => 'oubli@test.com', 'token' => $jeton, 'password' => 'nouveau-mdp-1', 'password_confirmation' => 'nouveau-mdp-1',
        ])->assertUnprocessable();
    }

    public function test_mot_de_passe_temporaire_bloque_l_application_jusqu_au_changement(): void
    {
        $this->compte->forceFill(['doit_changer_mot_de_passe' => true])->save();
        Sanctum::actingAs($this->compte);

        $this->getJson('/api/classes')->assertForbidden()->assertJsonPath('code', 'mot_de_passe_a_changer');

        // Mauvais mot de passe actuel : refusé, le blocage reste.
        $this->postJson('/api/mot-de-passe/changer', [
            'actuel' => 'mauvais', 'nouveau' => 'nouveau-mdp-2', 'nouveau_confirmation' => 'nouveau-mdp-2',
        ])->assertUnprocessable();
        $this->getJson('/api/classes')->assertForbidden();

        // Bon mot de passe actuel : le blocage est levé.
        $this->postJson('/api/mot-de-passe/changer', [
            'actuel' => 'ancien-mdp', 'nouveau' => 'nouveau-mdp-2', 'nouveau_confirmation' => 'nouveau-mdp-2',
        ])->assertOk();
        $this->assertFalse((bool) $this->compte->fresh()->doit_changer_mot_de_passe);
        $this->getJson('/api/classes')->assertOk();
    }
}
