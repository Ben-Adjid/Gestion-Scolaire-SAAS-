<?php

namespace Tests\Unit;

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

    public function test_calcule_moyenne_generale_ponderee(): void
    {
        $classe = Classe::factory()->create();
        $etudiant = Etudiant::factory()->create(['classe_id' => $classe->id]);
        $math = Matiere::factory()->create(['nom' => 'Maths', 'coefficient' => 4]);
        $eps = Matiere::factory()->create(['nom' => 'EPS', 'coefficient' => 1]);

        Note::create(['etudiant_id' => $etudiant->id, 'matiere_id' => $math->id, 'trimestre' => 1, 'type' => 'devoir', 'valeur' => 15]);
        Note::create(['etudiant_id' => $etudiant->id, 'matiere_id' => $eps->id, 'trimestre' => 1, 'type' => 'devoir', 'valeur' => 10]);

        $bulletin = app(BulletinService::class)->generateBulletin($etudiant, 1);

        // (15*4 + 10*1) / 5 = 14
        $this->assertEquals(14.0, $bulletin['moyenne_generale']);
        $this->assertEquals(1, $bulletin['rang']);
        $this->assertEquals("Tableau d'honneur", $bulletin['mention']['label']);
    }

    public function test_mentions_et_decisions(): void
    {
        $service = app(BulletinService::class);

        $this->assertEquals('Félicitations', $service->mention(16.5)['label']);
        $this->assertEquals("Tableau d'honneur", $service->mention(14.5)['label']);
        $this->assertEquals('Encouragements', $service->mention(12)['label']);
        $this->assertEquals('Passable', $service->mention(10)['label']);
        $this->assertEquals('Insuffisant', $service->mention(8)['label']);

        $this->assertEquals('admis', $service->decision(12)['statut']);
        $this->assertEquals('redouble', $service->decision(8)['statut']);
        $this->assertEquals('inconnu', $service->decision(null)['statut']);
    }

    public function test_appreciation_note(): void
    {
        $service = app(BulletinService::class);

        $this->assertEquals('Excellent', $service->appreciationNote(17));
        $this->assertEquals('Très bien', $service->appreciationNote(14));
        $this->assertEquals('Doit progresser', $service->appreciationNote(6));
    }
}
