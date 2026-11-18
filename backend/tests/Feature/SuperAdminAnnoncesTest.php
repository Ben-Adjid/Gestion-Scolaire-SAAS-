<?php

namespace Tests\Feature;

use App\Models\Etablissement;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SuperAdminAnnoncesTest extends TestCase
{
    use RefreshDatabase;

    public function test_annonce_ciblee_par_plan_n_atteint_que_les_admins_concernes(): void
    {
        $vip = Etablissement::create(['nom' => 'VIP', 'plan' => 'vip', 'statut_abonnement' => 'actif']);
        $standard = Etablissement::create(['nom' => 'Standard', 'plan' => 'standard', 'statut_abonnement' => 'actif']);
        $adminVip = User::factory()->create(['role' => 'admin', 'etablissement_id' => $vip->id]);
        $adminStd = User::factory()->create(['role' => 'admin', 'etablissement_id' => $standard->id]);

        Sanctum::actingAs(User::factory()->create(['role' => 'super_admin']));
        $this->postJson('/api/super-admin/annonces', [
            'titre' => 'Mise à jour', 'contenu' => 'Nouveautés VIP', 'cible' => 'plan', 'plan' => 'vip',
        ])->assertCreated();

        $this->assertTrue(Message::where('recipient_id', $adminVip->id)->exists());
        $this->assertFalse(Message::where('recipient_id', $adminStd->id)->exists());
    }

    public function test_seul_le_super_admin_peut_annoncer(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'etablissement_id' => 1]));
        $this->postJson('/api/super-admin/annonces', ['titre' => 'x', 'contenu' => 'y', 'cible' => 'tous'])->assertForbidden();
    }

    public function test_plan_inconnu_refuse(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'super_admin']));
        $this->postJson('/api/super-admin/annonces', ['titre' => 'x', 'contenu' => 'y', 'cible' => 'plan', 'plan' => 'premium'])
            ->assertStatus(422);
    }
}
