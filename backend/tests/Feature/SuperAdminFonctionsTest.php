<?php

namespace Tests\Feature;

use App\Models\Audit;
use App\Models\Etablissement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Fonctions de la plateforme : traçabilité, suppression sécurisée, export. */
class SuperAdminFonctionsTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->superAdmin = User::factory()->create(['role' => 'super_admin', 'etablissement_id' => null]);
        Sanctum::actingAs($this->superAdmin);
    }

    public function test_creation_et_suspension_sont_journalisees(): void
    {
        $this->postJson('/api/super-admin/etablissements', [
            'nom' => 'Lycée Test', 'plan' => 'vip',
            'admin_name' => 'Chef', 'admin_email' => 'chef@lycee-test.com', 'admin_password' => 'secret123',
        ])->assertCreated();

        $etab = Etablissement::where('nom', 'Lycée Test')->firstOrFail();
        $this->postJson("/api/super-admin/etablissements/{$etab->id}/suspendre")->assertOk();

        $this->assertDatabaseHas('audits', ['user_id' => $this->superAdmin->id, 'action' => 'creation_etablissement']);
        $this->assertDatabaseHas('audits', ['user_id' => $this->superAdmin->id, 'action' => 'suspension_etablissement']);
    }

    public function test_suppression_exige_le_nom_exact(): void
    {
        $etab = Etablissement::create(['nom' => 'À supprimer', 'plan' => 'standard', 'statut_abonnement' => 'actif']);

        $this->deleteJson("/api/super-admin/etablissements/{$etab->id}", ['confirmation' => 'autre nom'])
            ->assertUnprocessable();
        $this->assertNotNull(Etablissement::find($etab->id));

        $this->deleteJson("/api/super-admin/etablissements/{$etab->id}", ['confirmation' => 'À supprimer'])
            ->assertOk();
        $this->assertNull(Etablissement::find($etab->id));
    }

    public function test_journal_ne_montre_que_les_actions_de_la_plateforme(): void
    {
        Audit::create(['user_id' => $this->superAdmin->id, 'action' => 'suspension_etablissement', 'module' => 'etablissements', 'description' => 'action plateforme']);
        $admin = User::factory()->create(['role' => 'admin', 'etablissement_id' => Etablissement::create(['nom' => 'E', 'plan' => 'standard', 'statut_abonnement' => 'actif'])->id]);
        Audit::create(['user_id' => $admin->id, 'action' => 'create', 'module' => 'Etudiant', 'description' => 'action école']);

        $descriptions = collect($this->getJson('/api/super-admin/journal')->assertOk()->json('data'))->pluck('description');
        $this->assertTrue($descriptions->contains('action plateforme'));
        $this->assertFalse($descriptions->contains('action école'));
    }

    /** Contenu XML de la première feuille d'un classeur .xlsx. */
    private function feuille(string $contenu): string
    {
        $chemin = tempnam(sys_get_temp_dir(), 'feuille');
        file_put_contents($chemin, $contenu);
        $zip = new \ZipArchive();
        $zip->open($chemin);
        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        @unlink($chemin);

        return (string) $xml;
    }

    public function test_export_excel_des_etablissements(): void
    {
        Etablissement::create(['nom' => 'Export École', 'plan' => 'vip', 'statut_abonnement' => 'actif']);

        $res = $this->get('/api/super-admin/etablissements/export')->assertOk();
        $this->assertStringContainsString('spreadsheetml.sheet', $res->headers->get('Content-Type'));
        $this->assertStringContainsString('Export École', $this->feuille($res->getContent()));
    }
}
