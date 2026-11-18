<?php

namespace Tests\Feature;

use App\Models\Depense;
use App\Models\Etablissement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use ZipArchive;

/** Exports Excel de la comptabilité et des dépenses (formule Entreprise). */
class ExportComptabiliteTest extends TestCase
{
    use RefreshDatabase;

    private function feuille(string $contenu): string
    {
        $chemin = tempnam(sys_get_temp_dir(), 'compta');
        file_put_contents($chemin, $contenu);
        $zip = new ZipArchive();
        $zip->open($chemin);
        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        @unlink($chemin);

        return (string) $xml;
    }

    private function gestionnaire(): void
    {
        $etab = Etablissement::create(['nom' => 'Compta', 'plan' => 'entreprise', 'statut_abonnement' => 'actif']);
        Sanctum::actingAs(User::factory()->create(['role' => 'gestionnaire', 'etablissement_id' => $etab->id]));
    }

    public function test_export_des_depenses_en_excel_avec_total(): void
    {
        $this->gestionnaire();
        Depense::create(['categorie' => 'salaires', 'libelle' => 'Salaire Octobre', 'montant' => 250000, 'date' => '2026-10-31']);
        Depense::create(['categorie' => 'entretien', 'libelle' => 'Peinture salle 3', 'montant' => 40000, 'date' => '2026-11-02']);

        $res = $this->get('/api/depenses/export')->assertOk();
        $this->assertStringContainsString('spreadsheetml.sheet', $res->headers->get('Content-Type'));

        $xml = $this->feuille($res->getContent());
        $this->assertStringContainsString('Salaire Octobre', $xml);
        $this->assertStringContainsString('Peinture salle 3', $xml);
        // Ligne de total : 290 000.
        $this->assertStringContainsString('<v>290000</v>', $xml);
    }

    public function test_export_du_recouvrement_par_classe(): void
    {
        $this->gestionnaire();

        $res = $this->get('/api/comptabilite/export')->assertOk();
        $this->assertStringContainsString('spreadsheetml.sheet', $res->headers->get('Content-Type'));
        $this->assertStringContainsString('Total', $this->feuille($res->getContent()));
    }

    public function test_export_comptable_refuse_hors_formule_entreprise(): void
    {
        $etab = Etablissement::create(['nom' => 'Standard', 'plan' => 'standard', 'statut_abonnement' => 'actif']);
        Sanctum::actingAs(User::factory()->create(['role' => 'gestionnaire', 'etablissement_id' => $etab->id]));

        $this->get('/api/depenses/export')->assertForbidden();
        $this->get('/api/comptabilite/export')->assertForbidden();
    }
}
