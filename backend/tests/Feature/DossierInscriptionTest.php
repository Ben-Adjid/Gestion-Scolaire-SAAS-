<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\Etablissement;
use App\Models\Etudiant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DossierInscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_secretaire_peut_enregistrer_le_dossier_d_inscription(): void
    {
        $secretaire = User::factory()->create(['role' => 'secretaire']);
        $etudiant = Etudiant::factory()->create();

        Sanctum::actingAs($secretaire);

        $this->postJson("/api/etudiants/{$etudiant->id}/dossier", [
            'acte_naissance' => true,
            'bulletin' => true,
            'photo' => false,
        ])->assertOk()
            ->assertJsonPath('dossier.complet', false);

        $this->assertDatabaseHas('dossiers_inscription', [
            'etudiant_id' => $etudiant->id,
            'acte_naissance' => true,
            'bulletin' => true,
            'photo' => false,
        ]);
    }

    public function test_lookup_retourne_le_dossier_existant(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $etudiant = Etudiant::factory()->create(['matricule' => 'GS-99999']);

        Sanctum::actingAs($admin);

        $this->postJson("/api/etudiants/{$etudiant->id}/dossier", [
            'acte_naissance' => true,
            'bulletin' => false,
            'photo' => true,
        ])->assertOk();

        $this->getJson('/api/etudiants/lookup?matricule=GS-99999')
            ->assertOk()
            ->assertJsonPath('dossier.acte_naissance', true)
            ->assertJsonPath('dossier.photo', true);
    }

    public function test_enseignant_ne_peut_pas_modifier_le_dossier(): void
    {
        $enseignant = User::factory()->create(['role' => 'enseignant']);
        $classe = Classe::factory()->create();
        $etudiant = Etudiant::factory()->create(['classe_id' => $classe->id]);

        Sanctum::actingAs($enseignant);

        $this->postJson("/api/etudiants/{$etudiant->id}/dossier", [
            'photo' => true,
        ])->assertStatus(403);
    }

    public function test_televersement_et_telechargement_prive_du_dossier(): void
    {
        Storage::fake('private');
        $etudiant = Etudiant::factory()->create();
        $admin = User::factory()->create([
            'role' => 'admin',
            'etablissement_id' => $etudiant->etablissement_id,
        ]);
        Sanctum::actingAs($admin);

        $this->post("/api/etudiants/{$etudiant->id}/dossier", [
            'acte_naissance' => true,
            'acte_naissance_file' => UploadedFile::fake()->createWithContent('acte.pdf', '%PDF-1.4 document'),
        ])->assertOk();

        $path = $etudiant->fresh()->dossierInscription->acte_naissance_path;
        Storage::disk('private')->assertExists($path);
        $this->get("/api/etudiants/{$etudiant->id}/dossier/fichiers/acte_naissance")
            ->assertOk()
            ->assertHeader('content-disposition');
        $this->get("/api/etudiants/{$etudiant->id}/dossier/fichiers/inconnu")->assertNotFound();
    }

    public function test_televersement_et_telechargement_prive_du_dossier_d_examen(): void
    {
        Storage::fake('private');
        $etablissement = Etablissement::create([
            'nom' => 'Lycée examens',
            'plan' => 'vip',
            'statut_abonnement' => 'actif',
        ]);
        $etudiant = Etudiant::factory()->create(['etablissement_id' => $etablissement->id]);
        Sanctum::actingAs(User::factory()->create([
            'role' => 'admin',
            'etablissement_id' => $etablissement->id,
        ]));

        $this->post("/api/dossiers-examen/{$etudiant->id}", [
            'examen' => 'bac',
            'acte_naissance' => true,
            'acte_naissance_file' => UploadedFile::fake()->createWithContent('acte.pdf', '%PDF-1.4 document'),
        ])->assertOk();

        $this->get("/api/dossiers-examen/{$etudiant->id}/fichiers/acte_naissance")
            ->assertOk()
            ->assertHeader('content-disposition');
        $this->get("/api/dossiers-examen/{$etudiant->id}/fichiers/unknown")->assertNotFound();
    }
}
