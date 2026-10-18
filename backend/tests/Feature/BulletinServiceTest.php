<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\Etudiant;
use App\Models\Matiere;
use App\Models\Note;
use App\Services\BulletinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulletinServiceTest extends TestCase
{
    use RefreshDatabase;

    private BulletinService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(BulletinService::class);
    }

    public function test_compute_class_averages(): void
    {
        $classe = Classe::factory()->create();
        $matiere1 = Matiere::factory()->create(['coefficient' => 2]);
        $matiere2 = Matiere::factory()->create(['coefficient' => 3]);
        $etudiant = Etudiant::factory()->create(['classe_id' => $classe->id]);

        Note::create(['etudiant_id' => $etudiant->id, 'matiere_id' => $matiere1->id, 'trimestre' => 1, 'type' => 'devoir', 'valeur' => 15]);
        Note::create(['etudiant_id' => $etudiant->id, 'matiere_id' => $matiere2->id, 'trimestre' => 1, 'type' => 'devoir', 'valeur' => 18]);

        // (15*2 + 18*3) / (2+3) = 16.8
        $averages = $this->service->computeClassAverages([$etudiant->id], 1);

        $this->assertEquals(16.8, round($averages[$etudiant->id], 2));
    }

    public function test_decision_admission(): void
    {
        $this->assertEquals('admis', $this->service->decision(15.0)['statut']);
        $this->assertEquals('redouble', $this->service->decision(8.0)['statut']);
        $this->assertEquals('admis', $this->service->decision(10.0)['statut']);
    }

    public function test_attribution_des_mentions(): void
    {
        $this->assertEquals('Insuffisant', $this->service->mention(9.0)['label']);
        $this->assertEquals('Encouragements', $this->service->mention(12.0)['label']);
        $this->assertEquals("Tableau d'honneur", $this->service->mention(14.0)['label']);
        $this->assertEquals('Félicitations', $this->service->mention(16.5)['label']);
    }

    public function test_structure_du_bulletin(): void
    {
        $classe = Classe::factory()->create();
        $matiere = Matiere::factory()->create();
        $etudiant = Etudiant::factory()->create(['classe_id' => $classe->id]);

        Note::create(['etudiant_id' => $etudiant->id, 'matiere_id' => $matiere->id, 'trimestre' => 1, 'type' => 'devoir', 'valeur' => 14]);

        $bulletin = $this->service->generateBulletin($etudiant, 1);

        foreach (['etudiant', 'matieres', 'moyenne_generale', 'decision', 'mention', 'rang'] as $cle) {
            $this->assertArrayHasKey($cle, $bulletin);
        }
        $this->assertEquals('admis', $bulletin['decision']['statut']);
    }

    public function test_bulletin_sans_notes(): void
    {
        $etudiant = Etudiant::factory()->create(['classe_id' => Classe::factory()->create()->id]);

        $bulletin = $this->service->generateBulletin($etudiant, 1);

        $this->assertEquals(0, $bulletin['moyenne_generale']);
        $this->assertEquals('inconnu', $bulletin['decision']['statut']);
    }

    public function test_rang_dans_la_classe(): void
    {
        $classe = Classe::factory()->create();
        $matiere = Matiere::factory()->create(['coefficient' => 1]);

        $eleves = collect([15, 12, 18])->map(function (int $valeur) use ($classe, $matiere) {
            $etudiant = Etudiant::factory()->create(['classe_id' => $classe->id]);
            Note::create(['etudiant_id' => $etudiant->id, 'matiere_id' => $matiere->id, 'trimestre' => 1, 'type' => 'devoir', 'valeur' => $valeur]);

            return $etudiant;
        });

        $this->assertEquals(1, $this->service->generateBulletin($eleves[2], 1)['rang']);
        $this->assertEquals(2, $this->service->generateBulletin($eleves[0], 1)['rang']);
        $this->assertEquals(3, $this->service->generateBulletin($eleves[1], 1)['rang']);
    }
}
