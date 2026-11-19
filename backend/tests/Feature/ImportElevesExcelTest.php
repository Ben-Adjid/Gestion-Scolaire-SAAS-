<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\Etablissement;
use App\Models\Etudiant;
use App\Models\User;
use App\Services\Xlsx;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Import des élèves depuis un fichier Excel : dates Excel, dates texte, erreurs signalées, modèle téléchargeable. */
class ImportElevesExcelTest extends TestCase
{
    use RefreshDatabase;

    private Etablissement $etab;

    protected function setUp(): void
    {
        parent::setUp();
        $this->etab = Etablissement::create(['nom' => 'École Import', 'plan' => 'vip', 'statut_abonnement' => 'actif', 'annee_scolaire' => '2026-2027']);
        Classe::create(['nom' => '6e A', 'niveau' => '6ème', 'etablissement_id' => $this->etab->id]);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'etablissement_id' => $this->etab->id]));
    }

    /** Fichier .xlsx réel (ZIP) avec les valeurs données, en-tête en première ligne. */
    private function fichierXlsx(array $entetes, array $lignes): UploadedFile
    {
        $contenu = Xlsx::generer('Élèves', $entetes, $lignes);
        $chemin = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
        file_put_contents($chemin, $contenu);

        return new UploadedFile($chemin, 'eleves.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    public function test_import_excel_avec_dates_excel_et_dates_texte(): void
    {
        $fichier = $this->fichierXlsx(
            ['Nom', 'Prénom', 'Classe', 'Date de naissance', 'Genre'],
            [
                ['Diop', 'Awa', '6e A', '36526', 'F'],          // nombre de jours Excel : 01/01/2000
                ['Ali', 'Moussa', '6e A', '15/03/2011', 'M'],   // texte jj/mm/aaaa
            ]
        );

        $this->postJson('/api/import/etudiants', ['fichier' => $fichier, 'mode' => 'importer'], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('importes', 2);

        $this->assertSame('2000-01-01', Etudiant::where('nom', 'Diop')->value('date_naissance')->toDateString());
        $this->assertSame('2011-03-15', Etudiant::where('nom', 'Ali')->value('date_naissance')->toDateString());
        $this->assertSame(Classe::where('nom', '6e A')->value('id'), Etudiant::where('nom', 'Diop')->value('classe_id'));
    }

    public function test_apercu_signale_les_lignes_en_erreur_sans_rien_enregistrer(): void
    {
        $fichier = $this->fichierXlsx(
            ['Nom', 'Prénom', 'Date de naissance'],
            [
                ['Sow', 'Ibrahima', '01/02/2012'],
                ['', 'Sans nom', '01/02/2012'],
                ['Ndiaye', 'Fatou', 'date-invalide'],
            ]
        );

        $reponse = $this->postJson('/api/import/etudiants', ['fichier' => $fichier, 'mode' => 'apercu'], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('total', 3)->assertJsonPath('valides', 1);
        $this->assertNotEmpty($reponse->json('erreurs'));
        $this->assertSame(0, Etudiant::count());
    }

    public function test_le_modele_telechargeable_se_relit_avec_les_intitules_reconnus(): void
    {
        $reponse = $this->get('/api/import/etudiants/modele')->assertOk();
        $this->assertStringContainsString('spreadsheetml', $reponse->headers->get('Content-Type'));

        $chemin = tempnam(sys_get_temp_dir(), 'mod').'.xlsx';
        file_put_contents($chemin, $reponse->getContent());
        [$entetes, $lignes] = Xlsx::lire($chemin);

        $this->assertSame(['Nom', 'Prénom', 'Classe', 'Date de naissance', 'Genre', 'Téléphone', 'Adresse'], $entetes);
        $this->assertSame([], $lignes, 'Le modèle ne doit contenir aucune donnée d’élève.');
    }

    public function test_le_numero_de_ligne_tient_compte_de_la_ligne_de_titre_du_modele(): void
    {
        // Fichier avec une ligne de titre (comme le modèle) : l'élève en erreur est à la ligne 5 du tableur.
        $contenu = Xlsx::generer('Élèves', ['Nom', 'Prénom'], [['Sow', 'Ibrahima'], ['Ba', 'Aminata'], ['', 'Sans nom']], [], 'Titre du modèle');
        $chemin = tempnam(sys_get_temp_dir(), 'titre').'.xlsx';
        file_put_contents($chemin, $contenu);
        $fichier = new UploadedFile($chemin, 'eleves.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $this->postJson('/api/import/etudiants', ['fichier' => $fichier, 'mode' => 'apercu'], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('erreurs.0.ligne', 5);
    }
}
