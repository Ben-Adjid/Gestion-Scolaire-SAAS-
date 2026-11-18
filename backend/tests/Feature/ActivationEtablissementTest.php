<?php

namespace Tests\Feature;

use App\Mail\ActivationCompte;
use App\Models\Etablissement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Création d'un établissement par le super admin : l'administrateur active son compte et choisit son mot de passe. */
class ActivationEtablissementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Sanctum::actingAs(User::factory()->create(['role' => 'super_admin', 'etablissement_id' => null]));
    }

    /** Extrait le jeton du lien d'activation envoyé par e-mail (le jeton n'est stocké que haché). */
    private function jetonEnvoye(): string
    {
        $jeton = null;
        Mail::assertSent(ActivationCompte::class, function (ActivationCompte $mail) use (&$jeton) {
            parse_str((string) parse_url($mail->lien, PHP_URL_QUERY), $query);
            $jeton = $query['token'];

            return str_contains($mail->lien, 'activation=1');
        });

        return $jeton;
    }

    public function test_creation_sans_mot_de_passe_envoie_une_invitation_et_le_compte_ne_s_ouvre_pas(): void
    {
        $this->postJson('/api/super-admin/etablissements', [
            'nom' => 'Collège Sahel', 'plan' => 'standard', 'admin_name' => 'Awa Diop', 'admin_email' => 'awa@sahel.test',
        ])->assertCreated();

        Mail::assertSent(ActivationCompte::class, fn ($m) => $m->hasTo('awa@sahel.test'));
        $admin = User::withoutGlobalScopes()->where('email', 'awa@sahel.test')->firstOrFail();
        $this->assertSame('admin', $admin->role);

        // Personne ne connaît le mot de passe : la connexion est refusée avant activation.
        $this->postJson('/api/login', ['email' => 'awa@sahel.test', 'password' => 'secret123'])->assertStatus(422);
    }

    public function test_l_administrateur_active_son_compte_avec_son_mot_de_passe(): void
    {
        $this->postJson('/api/super-admin/etablissements', [
            'nom' => 'Lycée Nord', 'plan' => 'vip', 'admin_name' => 'Moussa', 'admin_email' => 'moussa@nord.test',
        ])->assertCreated();
        $jeton = $this->jetonEnvoye();

        $this->postJson('/api/mot-de-passe/activer', [
            'email' => 'moussa@nord.test', 'token' => $jeton, 'password' => 'MonChoix2026', 'password_confirmation' => 'MonChoix2026',
        ])->assertOk();

        $this->postJson('/api/login', ['email' => 'moussa@nord.test', 'password' => 'MonChoix2026'])->assertOk()->assertJsonStructure(['token']);
        // Le jeton ne sert qu'une fois.
        $this->postJson('/api/mot-de-passe/activer', [
            'email' => 'moussa@nord.test', 'token' => $jeton, 'password' => 'Autre2026', 'password_confirmation' => 'Autre2026',
        ])->assertStatus(422);
    }

    public function test_un_mauvais_ou_ancien_lien_d_activation_est_refuse(): void
    {
        $this->postJson('/api/super-admin/etablissements', [
            'nom' => 'École Est', 'plan' => 'standard', 'admin_name' => 'Ali', 'admin_email' => 'ali@est.test',
        ])->assertCreated();
        $jeton = $this->jetonEnvoye();

        $this->postJson('/api/mot-de-passe/activer', [
            'email' => 'ali@est.test', 'token' => 'faux', 'password' => 'MonChoix2026', 'password_confirmation' => 'MonChoix2026',
        ])->assertStatus(422);

        // Lien vieux de plus de 72 heures.
        DB::table('password_reset_tokens')->where('email', 'activation:ali@est.test')->update(['created_at' => now()->subHours(73)]);
        $this->postJson('/api/mot-de-passe/activer', [
            'email' => 'ali@est.test', 'token' => $jeton, 'password' => 'MonChoix2026', 'password_confirmation' => 'MonChoix2026',
        ])->assertStatus(422);
    }

    public function test_un_lien_de_reinitialisation_n_active_pas_un_compte(): void
    {
        $this->postJson('/api/super-admin/etablissements', [
            'nom' => 'École Ouest', 'plan' => 'standard', 'admin_name' => 'Fatou', 'admin_email' => 'fatou@ouest.test',
        ])->assertCreated();
        $jeton = Str::random(64);
        DB::table('password_reset_tokens')->insert(['email' => 'fatou@ouest.test', 'token' => Hash::make($jeton), 'created_at' => now()]);

        $this->postJson('/api/mot-de-passe/activer', [
            'email' => 'fatou@ouest.test', 'token' => $jeton, 'password' => 'MonChoix2026', 'password_confirmation' => 'MonChoix2026',
        ])->assertStatus(422);
    }

    public function test_renvoi_de_l_invitation_depuis_la_liste_des_ecoles(): void
    {
        $etab = Etablissement::create(['nom' => 'École Renvoi', 'plan' => 'standard', 'statut_abonnement' => 'actif']);
        User::withoutGlobalScopes()->create([
            'name' => 'Admin', 'email' => 'admin@renvoi.test', 'password' => Hash::make(Str::random(40)),
            'role' => 'admin', 'etablissement_id' => $etab->id,
        ]);

        $this->postJson("/api/super-admin/etablissements/{$etab->id}/renvoyer-activation")->assertOk();
        Mail::assertSent(ActivationCompte::class, fn ($m) => $m->hasTo('admin@renvoi.test'));
    }

    public function test_creation_avec_mot_de_passe_garde_le_parcours_direct(): void
    {
        $this->postJson('/api/super-admin/etablissements', [
            'nom' => 'École Directe', 'plan' => 'standard', 'admin_name' => 'Chef', 'admin_email' => 'chef@directe.test', 'admin_password' => 'secret123',
        ])->assertCreated();

        $this->postJson('/api/login', ['email' => 'chef@directe.test', 'password' => 'secret123'])->assertOk();
    }
}
