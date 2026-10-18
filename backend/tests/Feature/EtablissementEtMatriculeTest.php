<?php

namespace Tests\Feature;

use App\Models\Etablissement;
use App\Models\Etudiant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EtablissementEtMatriculeTest extends TestCase
{
    use RefreshDatabase;

    public function test_matricule_est_generé_automatiquement(): void
    {
        Etablissement::create(['nom' => 'Lycée Test', 'prefixe_matricule' => 'LT']);

        $etudiant = Etudiant::create([
            'nom' => 'Ndiaye',
            'prenom' => 'Moussa',
        ]);

        $this->assertNotNull($etudiant->matricule);
        $this->assertStringStartsWith('LT-'.now()->format('Y').'-', $etudiant->matricule);
    }

    public function test_matricule_explicite_est_conservé(): void
    {
        $etudiant = Etudiant::create([
            'matricule' => 'GS-00001',
            'nom' => 'Ndiaye',
            'prenom' => 'Moussa',
        ]);

        $this->assertEquals('GS-00001', $etudiant->matricule);
    }

    public function test_admin_peut_mettre_a_jour_l_etablissement(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $this->postJson('/api/etablissement', [
            'nom' => 'Lycée Moderne',
            'devise' => 'Unité — Travail — Progrès',
            'ministere' => "Ministère de l'Éducation Nationale",
            'annee_scolaire' => '2026-2027',
            'prefixe_matricule' => 'LM',
        ])->assertOk()
            ->assertJsonPath('etablissement.nom', 'Lycée Moderne');

        $this->assertDatabaseHas('etablissements', ['nom' => 'Lycée Moderne']);
    }

    public function test_etablissement_est_accessible_aux_utilisateurs_authentifies(): void
    {
        $user = User::factory()->create(['role' => 'censeur']);
        Sanctum::actingAs($user);

        $this->getJson('/api/etablissement')->assertOk();
    }
}
