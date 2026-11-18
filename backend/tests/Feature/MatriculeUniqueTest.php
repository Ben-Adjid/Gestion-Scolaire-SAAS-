<?php

namespace Tests\Feature;

use App\Models\Etablissement;
use App\Models\Etudiant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Le matricule est unique sur toute la base, même si deux écoles ont le même préfixe. */
class MatriculeUniqueTest extends TestCase
{
    use RefreshDatabase;

    public function test_deux_ecoles_au_meme_prefixe_ne_recoivent_pas_le_meme_matricule(): void
    {
        $ecole1 = Etablissement::create(['nom' => 'École 1', 'plan' => 'standard', 'statut_abonnement' => 'actif', 'prefixe_matricule' => 'LIC']);
        $ecole2 = Etablissement::create(['nom' => 'École 2', 'plan' => 'standard', 'statut_abonnement' => 'actif', 'prefixe_matricule' => 'LIC']);

        $annee = now()->format('Y');
        Etudiant::withoutGlobalScopes()->create([
            'nom' => 'A', 'prenom' => 'A', 'genre' => 'M', 'etablissement_id' => $ecole2->id,
            'matricule' => "LIC-{$annee}-0001", 'annee_scolaire' => '2026-2027',
        ]);

        $suivant = Etudiant::generateMatricule($ecole1->id);

        $this->assertSame("LIC-{$annee}-0002", $suivant);
    }
}
