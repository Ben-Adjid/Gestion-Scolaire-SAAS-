<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\Etablissement;
use App\Models\Etudiant;
use App\Models\Matiere;
use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Graphique par classe : moyennes calculées par classe et par trimestre, à partir des notes réelles. */
class AnalyseParClasseTest extends TestCase
{
    use RefreshDatabase;

    public function test_moyennes_par_classe_et_par_trimestre(): void
    {
        $etab = Etablissement::create(['nom' => 'Analyse', 'plan' => 'vip', 'statut_abonnement' => 'actif']);
        $classe = Classe::factory()->create(['etablissement_id' => $etab->id, 'nom' => 'Graphique A']);
        $eleve = Etudiant::factory()->create(['etablissement_id' => $etab->id, 'classe_id' => $classe->id]);
        $matiere = Matiere::factory()->create(['etablissement_id' => $etab->id]);

        // Trimestre 1 : 10 et 14 → moyenne 12. Trimestre 2 : 8 → moyenne 8.
        foreach ([[1, 10], [1, 14], [2, 8]] as [$trimestre, $valeur]) {
            Note::create([
                'etudiant_id' => $eleve->id, 'matiere_id' => $matiere->id, 'trimestre' => $trimestre,
                'type' => 'devoir', 'valeur' => $valeur, 'statut' => 'valide', 'etablissement_id' => $etab->id,
            ]);
        }

        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'etablissement_id' => $etab->id]));

        $lignes = collect($this->getJson('/api/analyses')->assertOk()->json('moyennes_classe_trimestre'));
        $t1 = $lignes->firstWhere('trimestre', 1);
        $t2 = $lignes->firstWhere('trimestre', 2);

        $this->assertSame('Graphique A', $t1['classe']);
        $this->assertEquals(12.0, (float) $t1['moyenne']);
        $this->assertEquals(2, (int) $t1['nb_notes']);
        $this->assertEquals(8.0, (float) $t2['moyenne']);
    }
}
