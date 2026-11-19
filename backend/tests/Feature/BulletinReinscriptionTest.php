<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\Etudiant;
use App\Models\Inscription;
use App\Models\Matiere;
use App\Models\Note;
use App\Models\User;
use App\Services\BulletinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Bulletin d'un élève réinscrit : classe, niveau et moyenne restent ceux de l'année notée. */
class BulletinReinscriptionTest extends TestCase
{
    use RefreshDatabase;

    private Etudiant $eleve;
    private Classe $seconde;
    private Classe $premiere;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seconde = Classe::factory()->create(['nom' => 'Seconde A', 'niveau' => 'Seconde']);
        $this->premiere = Classe::factory()->create(['nom' => 'Première A', 'niveau' => 'Première', 'etablissement_id' => $this->seconde->etablissement_id]);
        $this->eleve = Etudiant::factory()->create(['classe_id' => $this->premiere->id, 'etablissement_id' => $this->seconde->etablissement_id]);

        // Année 2025-2026 : inscrit en Seconde A, noté.
        Inscription::create(['etudiant_id' => $this->eleve->id, 'classe_id' => $this->seconde->id, 'annee_scolaire' => '2025-2026',
            'type' => 'nouveau', 'date_inscription' => '2025-09-01', 'statut' => 'active', 'etablissement_id' => $this->seconde->etablissement_id]);
        $matiere = Matiere::factory()->create(['nom' => 'Mathématiques', 'coefficient' => 2, 'etablissement_id' => $this->seconde->etablissement_id]);
        Note::create(['etudiant_id' => $this->eleve->id, 'matiere_id' => $matiere->id, 'trimestre' => 1, 'type' => 'devoir',
            'valeur' => 13, 'annee_scolaire' => '2025-2026']);

        // Réinscription 2026-2027 en Première A, pas encore notée.
        Inscription::create(['etudiant_id' => $this->eleve->id, 'classe_id' => $this->premiere->id, 'annee_scolaire' => '2026-2027',
            'type' => 'ancien', 'date_inscription' => '2026-09-01', 'statut' => 'active', 'etablissement_id' => $this->seconde->etablissement_id]);
    }

    public function test_l_annee_de_reference_est_la_derniere_annee_notee(): void
    {
        $service = app(BulletinService::class);

        $this->assertSame('2025-2026', $service->anneeDeReference($this->eleve));
        $this->assertSame($this->seconde->id, $service->classeDeReference($this->eleve, '2025-2026'));
    }

    public function test_le_bulletin_affiche_la_classe_et_la_moyenne_de_l_annee_notee(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'etablissement_id' => $this->seconde->etablissement_id]));

        $bulletin = $this->getJson("/api/etudiants/{$this->eleve->id}/bulletin?trimestre=1")->assertOk()->json();

        $this->assertSame('Seconde A', $bulletin['classe']['nom']);
        $this->assertSame('Seconde', $bulletin['classe']['niveau']);
        $this->assertEquals(13, $bulletin['moyenne_generale']);
    }
}
