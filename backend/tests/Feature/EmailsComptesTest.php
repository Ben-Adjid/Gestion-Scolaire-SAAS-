<?php

namespace Tests\Feature;

use App\Mail\CompteCree;
use App\Models\Etablissement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** E-mails de création de compte : envoyés, sans mot de passe dans le message. */
class EmailsComptesTest extends TestCase
{
    use RefreshDatabase;

    public function test_creation_d_un_compte_envoie_un_mail_de_bienvenue_sans_mot_de_passe(): void
    {
        Mail::fake();
        $etab = Etablissement::create(['nom' => 'Mail École', 'plan' => 'vip', 'statut_abonnement' => 'actif']);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'etablissement_id' => $etab->id]));

        $this->postJson('/api/users', [
            'name' => 'Nouvel Agent', 'email' => 'nouvel.agent@test.com', 'role' => 'secretaire', 'password' => 'secret123',
        ])->assertCreated();

        Mail::assertSent(CompteCree::class, function (CompteCree $mail) {
            $this->assertStringNotContainsString('secret123', $mail->render());

            return $mail->hasTo('nouvel.agent@test.com') && str_contains($mail->render(), 'Mail École');
        });
    }

    public function test_creation_d_une_ecole_envoie_un_mail_a_son_administrateur(): void
    {
        Mail::fake();
        Sanctum::actingAs(User::factory()->create(['role' => 'super_admin', 'etablissement_id' => null]));

        $this->postJson('/api/super-admin/etablissements', [
            'nom' => 'Nouvelle École', 'plan' => 'standard',
            'admin_name' => 'Chef', 'admin_email' => 'chef@nouvelle-ecole.test', 'admin_password' => 'secret123',
        ])->assertCreated();

        Mail::assertSent(CompteCree::class, fn ($mail) => $mail->hasTo('chef@nouvelle-ecole.test'));
    }
}
