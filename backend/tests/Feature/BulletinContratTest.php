<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\Enseignant;
use App\Models\Etudiant;
use App\Models\Matiere;
use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BulletinContratTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulletin_fournit_les_champs_attendus_par_la_page(): void
    {
        $classe = Classe::factory()->create();
        $matiere = Matiere::factory()->create(['nom' => 'Mathématiques', 'coefficient' => 3]);
        $eleve = Etudiant::factory()->create(['classe_id' => $classe->id]);
        $autre = Etudiant::factory()->create(['classe_id' => $classe->id]);

        Note::create(['etudiant_id' => $eleve->id, 'matiere_id' => $matiere->id, 'trimestre' => 1, 'type' => 'devoir', 'valeur' => 14]);
        Note::create(['etudiant_id' => $autre->id, 'matiere_id' => $matiere->id, 'trimestre' => 1, 'type' => 'devoir', 'valeur' => 8]);

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $bulletin = $this->getJson("/api/etudiants/{$eleve->id}/bulletin?trimestre=1")->assertOk()->json();

        $this->assertEquals(2, $bulletin['effectif']);
        $this->assertEquals('admis', $bulletin['decision']['statut']);
        $ligne = $bulletin['matieres'][0];
        $this->assertEquals('Mathématiques', $ligne['matiere']['nom']);
        $this->assertEquals(14, $ligne['moyenne']);
        $this->assertEquals(11, $ligne['moyenne_classe']);
        $this->assertEquals(8, $ligne['min']);
        $this->assertEquals(14, $ligne['max']);
        $this->assertNotEmpty($ligne['appreciation']);
    }
}
