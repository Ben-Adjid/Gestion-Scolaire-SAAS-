<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Classe;
use App\Models\Etudiant;
use App\Models\Absence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AbsencesTest extends TestCase
{
    use RefreshDatabase;

    private $enseignant;
    private $cpe;
    private $classe;
    private $etudiant;

    protected function setUp(): void
    {
        parent::setUp();

        $profil = \App\Models\Enseignant::factory()->create();
        $this->enseignant = User::factory()->create(['role' => 'enseignant', 'enseignant_id' => $profil->id]);
        $this->cpe = User::factory()->create(['role' => 'cpe']);
        $this->classe = Classe::factory()->create();
        $profil->classes()->attach($this->classe->id);
        $this->etudiant = Etudiant::factory()->create(['classe_id' => $this->classe->id]);
    }

    public function test_enseignant_enregistre_absence()
    {
        $this->actingAs($this->enseignant)
            ->postJson('/api/absences', [
                'etudiant_id' => $this->etudiant->id,
                'date' => today(),
                'heure_debut' => '08:00',
                'heure_fin' => '10:00',
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('absences', [
            'etudiant_id' => $this->etudiant->id,
        ]);
    }

    public function test_absence_non_justifiee_par_defaut()
    {
        $this->actingAs($this->enseignant)
            ->postJson('/api/absences', [
                'etudiant_id' => $this->etudiant->id,
                'date' => today(),
                'heure_debut' => '08:00',
                'heure_fin' => '10:00',
            ]);

        $absence = Absence::latest()->first();
        $this->assertFalse($absence->justifie);
    }

    public function test_alertes_declenches_a_3_absences()
    {
        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($this->enseignant)
                ->postJson('/api/absences', [
                    'etudiant_id' => $this->etudiant->id,
                    'date' => today(),
                    'heure_debut' => '08:00',
                    'heure_fin' => '10:00',
                ])
                ->assertStatus(201);
        }

        $alertCount = \App\Models\Alerte::where('etudiant_id', $this->etudiant->id)
            ->where('etablissement_id', $this->etudiant->etablissement_id)
            ->count();
        $this->assertGreaterThanOrEqual(1, $alertCount);
    }
}
