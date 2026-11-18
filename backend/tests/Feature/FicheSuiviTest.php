<?php

namespace Tests\Feature;

use App\Models\Absence;
use App\Models\Classe;
use App\Models\Etablissement;
use App\Models\Etudiant;
use App\Models\Matiere;
use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FicheSuiviTest extends TestCase
{
    use RefreshDatabase;

    public function test_cpe_standard_peut_consulter_notes_absences_et_retards_de_son_etablissement(): void
    {
        $etablissement = Etablissement::create([
            'nom' => 'Établissement Standard',
            'plan' => 'standard',
            'statut_abonnement' => 'actif',
        ]);
        $classe = Classe::factory()->create(['etablissement_id' => $etablissement->id]);
        $etudiant = Etudiant::factory()->create([
            'etablissement_id' => $etablissement->id,
            'classe_id' => $classe->id,
        ]);
        $matiere = Matiere::factory()->create(['etablissement_id' => $etablissement->id]);
        Note::create([
            'etudiant_id' => $etudiant->id,
            'matiere_id' => $matiere->id,
            'trimestre' => 1,
            'type' => 'devoir',
            'valeur' => 14,
            'statut' => 'valide',
        ]);
        Absence::create([
            'etudiant_id' => $etudiant->id,
            'date' => today()->toDateString(),
            'type' => 'absence',
            'justifie' => true,
        ]);
        Absence::create([
            'etudiant_id' => $etudiant->id,
            'date' => today()->toDateString(),
            'type' => 'retard',
            'justifie' => false,
        ]);

        Sanctum::actingAs(User::factory()->create([
            'role' => 'cpe',
            'etablissement_id' => $etablissement->id,
        ]));

        $this->getJson("/api/etudiants/{$etudiant->id}/suivi")
            ->assertOk()
            ->assertJsonPath('eleve.id', $etudiant->id)
            ->assertJsonPath('synthese.notes', 1)
            ->assertJsonPath('synthese.moyenne', 14)
            ->assertJsonPath('synthese.absences', 1)
            ->assertJsonPath('synthese.retards', 1)
            ->assertJsonPath('notes.0.statut', 'valide')
            ->assertJsonMissingPath('frais');
    }

    public function test_fiche_suivi_est_limitee_au_tenant_et_incluse_en_standard(): void
    {
        $standard = Etablissement::create([
            'nom' => 'École Standard',
            'plan' => 'standard',
            'statut_abonnement' => 'actif',
        ]);
        $autre = Etablissement::create([
            'nom' => 'École VIP',
            'plan' => 'vip',
            'statut_abonnement' => 'actif',
        ]);
        $eleveAutre = Etudiant::factory()->create([
            'etablissement_id' => $autre->id,
            'classe_id' => Classe::factory()->create(['etablissement_id' => $autre->id])->id,
        ]);
        Sanctum::actingAs(User::factory()->create([
            'role' => 'cpe',
            'etablissement_id' => $standard->id,
        ]));

        $this->getJson("/api/etudiants/{$eleveAutre->id}/suivi")->assertNotFound();

        $eleveStandard = Etudiant::factory()->create([
            'etablissement_id' => $standard->id,
            'classe_id' => Classe::factory()->create(['etablissement_id' => $standard->id])->id,
        ]);
        $this->getJson("/api/etudiants/{$eleveStandard->id}/suivi")->assertOk();
    }
}
