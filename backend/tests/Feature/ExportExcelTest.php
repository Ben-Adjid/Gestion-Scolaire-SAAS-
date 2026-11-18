<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\Etablissement;
use App\Models\Etudiant;
use App\Models\User;
use App\Services\Xlsx;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use ZipArchive;

/** Export Excel (.xlsx) : archive valide, XML bien formé, données et mise en forme présentes. */
class ExportExcelTest extends TestCase
{
    use RefreshDatabase;

    private function classeur(string $contenu): array
    {
        $chemin = tempnam(sys_get_temp_dir(), 'test-xlsx');
        file_put_contents($chemin, $contenu);
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($chemin) === true, 'Le fichier doit être une archive ZIP valide.');

        $fichiers = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nom = $zip->getNameIndex($i);
            $fichiers[$nom] = $zip->getFromName($nom);
        }
        $zip->close();
        @unlink($chemin);

        return $fichiers;
    }

    public function test_le_classeur_est_une_archive_xlsx_bien_formee(): void
    {
        $contenu = Xlsx::generer('Élèves', ['Nom', 'Note'], [['Dupont & fils', 12.5], ['<Test>', 7]], ['Note']);
        $fichiers = $this->classeur($contenu);

        foreach (['[Content_Types].xml', '_rels/.rels', 'xl/workbook.xml', 'xl/styles.xml', 'xl/worksheets/sheet1.xml'] as $requis) {
            $this->assertArrayHasKey($requis, $fichiers);
            $this->assertNotFalse(simplexml_load_string($fichiers[$requis]), "XML invalide : {$requis}");
        }
        // Caractères spéciaux échappés, donnée numérique typée.
        $this->assertStringContainsString('Dupont &amp; fils', $fichiers['xl/worksheets/sheet1.xml']);
        $this->assertStringContainsString('&lt;Test&gt;', $fichiers['xl/worksheets/sheet1.xml']);
        $this->assertStringContainsString('<v>12.5</v>', $fichiers['xl/worksheets/sheet1.xml']);
        // En-tête figée et filtre automatique.
        $this->assertStringContainsString('state="frozen"', $fichiers['xl/worksheets/sheet1.xml']);
        $this->assertStringContainsString('<autoFilter ref="A1:B3"/>', $fichiers['xl/worksheets/sheet1.xml']);
    }

    public function test_export_des_eleves_au_format_excel_par_defaut(): void
    {
        $etab = Etablissement::create(['nom' => 'Excel', 'plan' => 'vip', 'statut_abonnement' => 'actif']);
        $classe = Classe::factory()->create(['etablissement_id' => $etab->id, 'nom' => '6ème A']);
        Etudiant::factory()->create(['etablissement_id' => $etab->id, 'classe_id' => $classe->id, 'nom' => 'Excelicien', 'prenom' => 'Eleve']);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'etablissement_id' => $etab->id]));

        $reponse = $this->get('/api/export/etudiants')->assertOk();
        $this->assertStringContainsString('spreadsheetml.sheet', $reponse->headers->get('Content-Type'));
        $this->assertStringContainsString('eleves.xlsx', $reponse->headers->get('Content-Disposition'));

        $fichiers = $this->classeur($reponse->getContent());
        $this->assertStringContainsString('Excelicien', $fichiers['xl/worksheets/sheet1.xml']);
    }

    public function test_le_csv_reste_disponible(): void
    {
        $etab = Etablissement::create(['nom' => 'Csv', 'plan' => 'vip', 'statut_abonnement' => 'actif']);
        Etudiant::factory()->create(['etablissement_id' => $etab->id, 'nom' => 'Csvien']);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'etablissement_id' => $etab->id]));

        $reponse = $this->get('/api/export/etudiants?format=csv')->assertOk();
        $this->assertStringContainsString('text/csv', $reponse->headers->get('Content-Type'));
        $this->assertStringContainsString('Csvien', $reponse->streamedContent());
    }
}
