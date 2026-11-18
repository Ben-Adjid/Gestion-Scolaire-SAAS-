<?php

namespace Tests\Feature;

use App\Models\Absence;
use App\Models\Classe;
use App\Models\Etablissement;
use App\Models\Etudiant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Parcours de formulaires restants : import et export des élèves, justification par un parent. */
class ParcoursFormulairesTest extends TestCase
{
    use RefreshDatabase;

    private Etablissement $etab;

    protected function setUp(): void
    {
        parent::setUp();
        $this->etab = Etablissement::create(['nom' => 'Parcours', 'plan' => 'vip', 'statut_abonnement' => 'actif', 'annee_scolaire' => '2026-2027']);
    }

    public function test_export_puis_import_des_eleves(): void
    {
        $classe = Classe::factory()->create(['etablissement_id' => $this->etab->id, 'nom' => '6ème A']);
        Etudiant::factory()->create(['etablissement_id' => $this->etab->id, 'classe_id' => $classe->id, 'nom' => 'Exporte', 'prenom' => 'Eleve']);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'etablissement_id' => $this->etab->id]));

        // Export : le CSV contient l'élève existant.
        $reponse = $this->get('/api/export/etudiants?format=csv')->assertOk();
        $csv = $reponse->streamedContent();
        $this->assertStringContainsString('Exporte', $csv);

        // Import : un fichier CSV crée un nouvel élève dans la classe indiquée.
        $fichier = UploadedFile::fake()->createWithContent('eleves.csv', "nom;prenom;classe;genre\nImporte;Nouveau;6ème A;M\n");
        // Étape 1 : aperçu sans écriture.
        $apercu = $this->post('/api/import/etudiants', ['fichier' => $fichier, 'mode' => 'apercu'])->assertSuccessful();
        $this->assertDatabaseMissing('etudiants', ['nom' => 'Importe']);

        // Étape 2 : import réel.
        $fichier = UploadedFile::fake()->createWithContent('eleves.csv', "nom;prenom;classe;genre
Importe;Nouveau;6ème A;M
");
        $this->post('/api/import/etudiants', ['fichier' => $fichier, 'mode' => 'importer'])->assertSuccessful();

        $this->assertDatabaseHas('etudiants', ['nom' => 'Importe', 'prenom' => 'Nouveau', 'etablissement_id' => $this->etab->id]);
    }

    public function test_parent_demande_la_justification_d_une_absence_de_son_enfant(): void
    {
        $classe = Classe::factory()->create(['etablissement_id' => $this->etab->id]);
        $enfant = Etudiant::factory()->create(['etablissement_id' => $this->etab->id, 'classe_id' => $classe->id]);
        $parent = User::factory()->create(['role' => 'parent', 'etablissement_id' => $this->etab->id]);
        $parent->enfants()->attach($enfant->id, ['lien' => 'parent']);
        $absence = Absence::create(['etudiant_id' => $enfant->id, 'date' => '2026-11-04', 'type' => 'absence', 'justifie' => false]);

        Sanctum::actingAs($parent);
        $this->postJson("/api/parent/enfants/{$enfant->id}/absences/{$absence->id}/justifier", ['motif' => 'Maladie, certificat fourni'])
            ->assertCreated()
            ->assertJsonPath('message', 'Demande envoyée au CPE.');
    }
}
