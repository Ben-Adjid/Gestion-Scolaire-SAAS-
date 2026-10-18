<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\Creneau;
use App\Models\EmploiDuTemps;
use App\Models\Enseignant;
use App\Models\Etablissement;
use App\Models\Matiere;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PointageTest extends TestCase
{
    use RefreshDatabase;

    private function makeEnseignant(int $jour = 1, string $heure = '08:00'): array
    {
        $classe = Classe::factory()->create();
        $matiere = Matiere::factory()->create();
        $enseignant = Enseignant::factory()->create();
        $user = User::factory()->create(['role' => 'enseignant', 'enseignant_id' => $enseignant->id]);

        $creneau = Creneau::create(['heure_debut' => $heure, 'heure_fin' => '09:00', 'ordre' => 1]);

        $edt = EmploiDuTemps::create([
            'classe_id' => $classe->id,
            'matiere_id' => $matiere->id,
            'enseignant_id' => $enseignant->id,
            'jour' => $jour,
            'creneau_id' => $creneau->id,
        ]);

        return ['user' => $user, 'edt' => $edt];
    }

    public function test_refuse_pointage_trop_tot(): void
    {
        $ctx = $this->makeEnseignant(1, '08:00');
        Sanctum::actingAs($ctx['user']);

        $this->travelTo('2025-09-01 07:00:00');

        $this->postJson('/api/pointages', ['emploi_du_temps_id' => $ctx['edt']->id])->assertStatus(422);
    }

    public function test_refuse_pointage_trop_tard(): void
    {
        $ctx = $this->makeEnseignant(1, '08:00');
        Sanctum::actingAs($ctx['user']);

        $this->travelTo('2025-09-01 08:20:00');

        $this->postJson('/api/pointages', ['emploi_du_temps_id' => $ctx['edt']->id])->assertStatus(422);
    }

    public function test_pointage_a_l_heure(): void
    {
        $ctx = $this->makeEnseignant(1, '08:00');
        Sanctum::actingAs($ctx['user']);

        $this->travelTo('2025-09-01 07:50:00');

        $this->postJson('/api/pointages', ['emploi_du_temps_id' => $ctx['edt']->id])
            ->assertOk()
            ->assertJsonPath('pointage.statut', 'a_l_heure');
    }

    public function test_pointage_en_retard(): void
    {
        $ctx = $this->makeEnseignant(1, '08:00');
        Sanctum::actingAs($ctx['user']);

        $this->travelTo('2025-09-01 08:05:00');

        $this->postJson('/api/pointages', ['emploi_du_temps_id' => $ctx['edt']->id])
            ->assertOk()
            ->assertJsonPath('pointage.statut', 'retard');
    }

    public function test_refuse_double_pointage_pour_le_meme_cours(): void
    {
        $ctx = $this->makeEnseignant(1, '08:00');
        Sanctum::actingAs($ctx['user']);

        $this->travelTo('2025-09-01 07:50:00');

        $this->postJson('/api/pointages', ['emploi_du_temps_id' => $ctx['edt']->id])->assertOk();
        $this->postJson('/api/pointages', ['emploi_du_temps_id' => $ctx['edt']->id])->assertStatus(422);
    }

    public function test_geoloc_hors_rayon_refuse(): void
    {
        config(['pointage.mode_test' => false]);

        Etablissement::create([
            'nom' => 'Lycée Test',
            'latitude' => 12.1064,
            'longitude' => 15.0445,
            'rayon_pointage' => 150,
        ]);

        $ctx = $this->makeEnseignant(1, '08:00');
        Sanctum::actingAs($ctx['user']);

        $this->travelTo('2025-09-01 07:50:00');

        $this->postJson('/api/pointages', [
            'emploi_du_temps_id' => $ctx['edt']->id,
            'latitude' => 13.0,
            'longitude' => 16.0,
        ])->assertStatus(422);
    }

    public function test_aujourdhui_liste_les_cours_du_jour(): void
    {
        $ctx = $this->makeEnseignant(1, '08:00');
        Sanctum::actingAs($ctx['user']);

        $this->travelTo('2025-09-01 07:50:00');

        $this->getJson('/api/pointages/aujourdhui')
            ->assertOk()
            ->assertJsonCount(1, 'cours')
            ->assertJsonPath('cours.0.pointable', true)
            ->assertJsonPath('cours.0.emploi_du_temps_id', $ctx['edt']->id);
    }
}
