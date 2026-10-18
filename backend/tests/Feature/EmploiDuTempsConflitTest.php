<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\Creneau;
use App\Models\Matiere;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmploiDuTempsConflitTest extends TestCase
{
    use RefreshDatabase;

    public function test_detecte_conflit_de_classe_sur_un_creneau(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $classe = Classe::factory()->create();
        $matiere = Matiere::factory()->create();
        $autreMatiere = Matiere::factory()->create();
        $creneau = Creneau::create(['heure_debut' => '08:00', 'heure_fin' => '09:00', 'ordre' => 1]);

        $payload = [
            'classe_id' => $classe->id,
            'matiere_id' => $matiere->id,
            'jour' => 1,
            'creneau_id' => $creneau->id,
            'salle' => 'S1',
        ];

        $this->postJson('/api/emploi-du-temps', $payload)->assertCreated();

        // Même classe, même jour, même créneau -> conflit
        $payload['matiere_id'] = $autreMatiere->id;

        $this->postJson('/api/emploi-du-temps', $payload)->assertStatus(422);
    }

    public function test_pas_de_conflit_sur_des_creneaux_differents(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $classe = Classe::factory()->create();
        $matiere = Matiere::factory()->create();
        $creneau1 = Creneau::create(['heure_debut' => '08:00', 'heure_fin' => '09:00', 'ordre' => 1]);
        $creneau2 = Creneau::create(['heure_debut' => '09:00', 'heure_fin' => '10:00', 'ordre' => 2]);

        $this->postJson('/api/emploi-du-temps', [
            'classe_id' => $classe->id,
            'matiere_id' => $matiere->id,
            'jour' => 1,
            'creneau_id' => $creneau1->id,
        ])->assertCreated();

        $this->postJson('/api/emploi-du-temps', [
            'classe_id' => $classe->id,
            'matiere_id' => $matiere->id,
            'jour' => 1,
            'creneau_id' => $creneau2->id,
        ])->assertCreated();
    }
}
