<?php

namespace Tests\Feature;

use App\Models\Etablissement;
use App\Models\Etudiant;
use App\Models\User;
use App\Services\Xlsx;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Import intelligent : Excel et CSV, reconnaissance des intitulés approximatifs, mappage manuel. */
class ImportIntelligentTest extends TestCase
{
    use RefreshDatabase;

    private Etablissement $etab;

    protected function setUp(): void
    {
        parent::setUp();
        $this->etab = Etablissement::create(['nom' => 'Import', 'plan' => 'entreprise', 'statut_abonnement' => 'actif']);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'etablissement_id' => $this->etab->id]));
    }

    private function xlsx(string $nom, array $entetes, array $lignes): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nom, Xlsx::generer('Élèves', $entetes, $lignes));
    }

    public function test_un_classeur_excel_se_relit_a_l_identique(): void
    {
        $chemin = tempnam(sys_get_temp_dir(), 'aller-retour');
        file_put_contents($chemin, Xlsx::generer('Élèves', ['Nom', 'Prénom', 'Note'], [['Dupont & Fils', 'Émile', '12.5'], ['Sy', 'Awa', '']]));

        [$entetes, $lignes] = Xlsx::lire($chemin);
        @unlink($chemin);

        $this->assertSame(['Nom', 'Prénom', 'Note'], $entetes);
        $this->assertSame(['Dupont & Fils', 'Émile', '12.5'], $lignes[0]);
        $this->assertSame(['Sy', 'Awa', ''], $lignes[1]);
    }

    public function test_apercu_d_un_fichier_excel_detecte_les_colonnes(): void
    {
        $fichier = $this->xlsx('eleves.xlsx', ['Nom', 'Prénom', 'Classe'], [['Ndiaye', 'Ali', '6ème A']]);

        $this->post('/api/import/etudiants', ['fichier' => $fichier, 'mode' => 'apercu'])
            ->assertOk()
            ->assertJsonPath('valides', 1)
            ->assertJsonPath('champs.0.champ', 'nom')
            ->assertJsonPath('champs.0.colonne', 0)
            ->assertJsonPath('champs.1.exemple', 'Ali');
    }

    public function test_reconnait_un_intitule_proche_par_une_faute_de_frappe(): void
    {
        $fichier = $this->xlsx('fautes.xlsx', ['Nom élevs', 'Prenom'], [['Fall', 'Mamadou']]);

        $this->post('/api/import/etudiants', ['fichier' => $fichier, 'mode' => 'apercu'])
            ->assertOk()
            ->assertJsonPath('valides', 1)
            ->assertJsonPath('champs.0.colonne', 0);
    }

    public function test_le_mappage_manuel_corrige_une_detection_erronee(): void
    {
        // Colonnes au nom ambigu : l'utilisateur indique lui-même où se trouvent le nom et le prénom.
        $fichier = $this->xlsx('ambigu.xlsx', ['Identifiant', 'Libellé', 'Autre'], [['Diop', 'Fatou', 'x']]);

        $this->post('/api/import/etudiants', [
            'fichier' => $fichier, 'mode' => 'apercu', 'mapping' => json_encode(['nom' => 0, 'prenom' => 1]),
        ])
            ->assertOk()
            ->assertJsonPath('valides', 1)
            ->assertJsonPath('champs.0.colonne', 0)
            ->assertJsonPath('champs.1.colonne', 1);
    }

    public function test_l_import_excel_cree_les_eleves(): void
    {
        $fichier = $this->xlsx('import.xlsx', ['Nom', 'Prénom', 'Classe', 'Genre'], [['Kone', 'Sali', '5ème A', 'F']]);

        $this->post('/api/import/etudiants', ['fichier' => $fichier, 'mode' => 'importer'])
            ->assertCreated()
            ->assertJsonPath('importes', 1);

        $this->assertDatabaseHas('etudiants', ['nom' => 'Kone', 'prenom' => 'Sali', 'etablissement_id' => $this->etab->id]);
        $this->assertSame(1, Etudiant::withoutGlobalScopes()->where('etablissement_id', $this->etab->id)->count());
    }
}
