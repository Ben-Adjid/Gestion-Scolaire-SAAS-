<?php

namespace Tests\Feature;

use App\Models\Etablissement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class ConnexionTelephoneTest extends TestCase
{
    use RefreshDatabase;

    private User $parent;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.whatsapp.token' => 'jeton-test', 'services.whatsapp.phone_id' => '123', 'services.whatsapp.otp_template' => 'code_connexion']);
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]], 200)]);
        RateLimiter::clear('otp-essais:1');

        $etab = Etablissement::create(['nom' => 'École Tel', 'plan' => 'vip', 'statut_abonnement' => 'actif']);
        $this->parent = User::factory()->create([
            'role' => 'parent', 'email' => 'hawa@tel.test', 'telephone' => '+235 66 12 34 56', 'etablissement_id' => $etab->id,
        ]);
    }

    /** Code envoyé par WhatsApp, récupéré depuis la requête envoyée à l'API. */
    private function codeEnvoye(): string
    {
        $code = null;
        Http::assertSent(function ($request) use (&$code) {
            if (str_contains($request->url(), 'graph.facebook.com')) {
                $code = $request['template']['components'][0]['parameters'][0]['text'];
            }

            return true;
        });

        return (string) $code;
    }

    public function test_le_code_part_par_whatsapp_et_connecte(): void
    {
        $this->postJson('/api/connexion/telephone/demander', ['telephone' => '66123456'])->assertOk();

        $code = $this->codeEnvoye();
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        Http::assertSent(fn ($r) => $r['type'] === 'template' && $r['to'] === '23566123456');

        $this->postJson('/api/connexion/telephone/verifier', ['telephone' => '+235 66 12 34 56', 'code' => $code])
            ->assertOk()->assertJsonStructure(['token', 'user']);

        // Le code ne sert qu'une fois.
        $this->postJson('/api/connexion/telephone/verifier', ['telephone' => '66123456', 'code' => $code])->assertStatus(422);
    }

    public function test_reponse_identique_pour_un_numero_inconnu_sans_envoi(): void
    {
        $this->postJson('/api/connexion/telephone/demander', ['telephone' => '99887766'])
            ->assertOk()->assertJsonPath('message', fn ($m) => str_contains($m, 'Si ce numéro'));
        Http::assertNothingSent();
    }

    public function test_code_faux_expire_ou_trop_d_essais(): void
    {
        $this->postJson('/api/connexion/telephone/demander', ['telephone' => '66123456'])->assertOk();
        $code = $this->codeEnvoye();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/connexion/telephone/verifier', ['telephone' => '66123456', 'code' => '000000'])->assertStatus(422);
        }
        // Même le bon code est refusé après le 5e essai : il faut redemander.
        $this->postJson('/api/connexion/telephone/verifier', ['telephone' => '66123456', 'code' => $code])->assertStatus(429);
    }

    public function test_code_expire_apres_dix_minutes(): void
    {
        $this->postJson('/api/connexion/telephone/demander', ['telephone' => '66123456'])->assertOk();
        $code = $this->codeEnvoye();
        DB::table('password_reset_tokens')->where('email', 'otp:'.$this->parent->id)->update(['created_at' => now()->subMinutes(11)]);

        $this->postJson('/api/connexion/telephone/verifier', ['telephone' => '66123456', 'code' => $code])->assertStatus(422);
    }

    public function test_sans_whatsapp_configure_aucun_code_et_message_clair(): void
    {
        config(['services.whatsapp.token' => null]);
        $this->postJson('/api/connexion/telephone/demander', ['telephone' => '66123456'])->assertOk();
        Http::assertNothingSent();
    }

    public function test_compte_avec_double_authentification_refuse_la_connexion_par_telephone(): void
    {
        $this->parent->forceFill(['two_factor_confirmed_at' => now()])->save();
        $this->postJson('/api/connexion/telephone/demander', ['telephone' => '66123456'])->assertOk();
        Http::assertNothingSent();
    }

    public function test_un_super_admin_ne_se_connecte_jamais_par_telephone(): void
    {
        User::factory()->create(['role' => 'super_admin', 'email' => 'plateforme@tel.test', 'telephone' => '+235 99 00 11 22', 'etablissement_id' => null]);
        $this->postJson('/api/connexion/telephone/demander', ['telephone' => '99001122'])->assertOk();
        Http::assertNothingSent();
    }
}
