<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\Etudiant;
use App\Models\Matiere;
use App\Models\Note;
use App\Models\Regle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PalmaresTest extends TestCase
{
    use RefreshDatabase;

    private function seedNotes(int $classeId, array $moyennes): void
    {
        $matiere = Matiere::factory()->create(['coefficient' => 1]);

        foreach ($moyennes as $moyenne) {
            $etudiant = Etudiant::factory()->create(['classe_id' => $classeId]);
            Note::create([
                'etudiant_id' => $etudiant->id,
                'matiere_id' => $matiere->id,
                'trimestre' => 1,
                'type' => 'devoir',
                'valeur' => $moyenne,
            ]);
        }
    }

    public function test_enseignant_n_a_pas_acces_au_tableau_d_honneur(): void
    {
        $enseignant = User::factory()->create(['role' => 'enseignant']);
        Sanctum::actingAs($enseignant);

        $this->getJson('/api/palmares')->assertStatus(403);
    }

    public function test_tableau_d_honneur_retourne_les_n_premiers_par_classe(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $classe = Classe::factory()->create();

        // 7 élèves avec des moyennes distinctes
        $this->seedNotes($classe->id, [10, 12, 8, 18, 15, 9, 14]);

        Sanctum::actingAs($admin);

        $res = $this->getJson('/api/palmares?trimestre=1')->assertOk();

        $this->assertArrayHasKey('tableau_honneur', $res->json());
        $this->assertCount(1, $res->json('tableau_honneur'));

        $eleves = $res->json('tableau_honneur.0.eleves');

        // Seuls les élèves avec une moyenne d'au moins 12 sont retenus (18, 15, 14, 12)
        $this->assertCount(4, $eleves);
        $this->assertEquals(18.0, $eleves[0]['moyenne']);
        $this->assertEquals(15.0, $eleves[1]['moyenne']);
        $this->assertEquals(14.0, $eleves[2]['moyenne']);
        $this->assertEquals(12.0, $eleves[3]['moyenne']);
    }

    public function test_les_eleves_sous_la_moyenne_de_12_ne_sont_pas_au_tableau(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $classe = Classe::factory()->create();

        // Tous sous 12 de moyenne -> personne au tableau d'honneur
        $this->seedNotes($classe->id, [8, 9, 10, 11]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/palmares?trimestre=1')
            ->assertOk()
            ->assertJsonCount(0, 'tableau_honneur');
    }

    public function test_nb_tableau_honneur_est_configurable(): void
    {
        Regle::create(['nb_tableau_honneur' => 3]);

        $admin = User::factory()->create(['role' => 'admin']);
        $classe = Classe::factory()->create();

        $this->seedNotes($classe->id, [10, 12, 8, 18, 15, 9, 14]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/palmares?trimestre=1')
            ->assertOk()
            ->assertJsonCount(3, 'tableau_honneur.0.eleves');
    }
}
