<?php

namespace Tests\Feature;

use App\Models\Audit;
use App\Models\Classe;
use App\Models\Etablissement;
use App\Models\Etudiant;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlansEtAuditTest extends TestCase
{
    use RefreshDatabase;

    private function etablissement(string $plan): Etablissement
    {
        return Etablissement::create(['nom' => "Lycée {$plan}", 'plan' => $plan, 'statut_abonnement' => 'actif']);
    }

    public function test_plan_standard_refuse_les_fonctionnalites_vip(): void
    {
        $etab = $this->etablissement('standard');
        $admin = User::factory()->create(['role' => 'admin', 'etablissement_id' => $etab->id]);
        Sanctum::actingAs($admin);

        $this->getJson('/api/analyses')
            ->assertForbidden()
            ->assertJsonPath('code', 'plan_insuffisant');
        $this->getJson('/api/frais-scolaires/stats')->assertOk();
    }

    public function test_standard_inclut_les_relances_et_notifications_aux_parents(): void
    {
        $etab = $this->etablissement('standard');
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'etablissement_id' => $etab->id]));

        $this->getJson('/api/frais-scolaires/relances')->assertOk();
        $this->assertContains('relances', $etab->fonctionnalites());
        $this->assertContains('notifications_parents', $etab->fonctionnalites());
        $this->assertContains('fiche_suivi', $etab->fonctionnalites());
        $this->assertContains('bulletin_personnalise', config('plans.plans.vip.fonctionnalites'));
        $this->assertNotContains('bulletin_personnalise', config('plans.plans.standard.fonctionnalites'));
    }

    public function test_plan_standard_refuse_examens_dossiers_et_journal(): void
    {
        $etab = $this->etablissement('standard');
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'etablissement_id' => $etab->id]));

        foreach (['/api/examens', '/api/dossiers-examen', '/api/audits'] as $url) {
            $this->getJson($url)
                ->assertForbidden()
                ->assertJsonPath('code', 'plan_insuffisant');
        }
    }

    public function test_plan_vip_donne_acces_au_conseil_et_aux_relances(): void
    {
        $etab = $this->etablissement('vip');
        $admin = User::factory()->create(['role' => 'admin', 'etablissement_id' => $etab->id]);
        Sanctum::actingAs($admin);

        $this->getJson('/api/frais-scolaires/relances')->assertOk();
        $this->getJson('/api/classes/'.Classe::factory()->create(['etablissement_id' => $etab->id])->id.'/conseil')->assertOk();
        $this->assertContains('bulletin_personnalise', $etab->fonctionnalites());
    }

    public function test_le_plan_est_expose_avec_ses_fonctionnalites(): void
    {
        $etab = $this->etablissement('vip');
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'etablissement_id' => $etab->id]));

        $this->getJson('/api/etablissement')
            ->assertOk()
            ->assertJsonPath('plan_label', 'VIP')
            ->assertJsonStructure(['formules' => ['standard', 'vip', 'entreprise']]);
        $this->assertContains('relances', $etab->fonctionnalites());
        $this->assertNotContains('api', $etab->fonctionnalites());
    }

    public function test_journal_d_audit_limite_a_son_etablissement(): void
    {
        $mon = $this->etablissement('vip');
        $autre = $this->etablissement('vip');
        $moi = User::factory()->create(['role' => 'admin', 'etablissement_id' => $mon->id]);
        $lui = User::factory()->create(['role' => 'admin', 'etablissement_id' => $autre->id]);

        AuditLogger::log($moi, 'create', 'Test', null, 'action du mien');
        AuditLogger::log($lui, 'create', 'Test', null, 'action de l autre');

        Sanctum::actingAs($moi);
        $descriptions = collect($this->getJson('/api/audits?per_page=100')->assertOk()->json('data'))->pluck('description');
        $this->assertTrue($descriptions->contains('action du mien'));
        $this->assertFalse($descriptions->contains('action de l autre'));

        // La plateforme ne lit pas le journal des écoles : ce sont des données d'établissement.
        Sanctum::actingAs(User::factory()->create(['role' => 'super_admin']));
        $this->assertCount(2, Audit::all());
        $this->getJson('/api/audits?per_page=100')
            ->assertForbidden()
            ->assertJsonPath('code', 'donnees_scolaires_reservees');
    }
}
