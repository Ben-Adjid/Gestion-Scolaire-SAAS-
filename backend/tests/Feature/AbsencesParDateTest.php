<?php

namespace Tests\Feature;

use App\Models\Absence;
use App\Models\Classe;
use App\Models\Etablissement;
use App\Models\Etudiant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** La page d'appel ne doit voir que les absences du jour choisi. */
class AbsencesParDateTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_liste_des_absences_se_filtre_par_jour(): void
    {
        $etab = Etablissement::create(['nom' => 'Dates', 'plan' => 'standard', 'statut_abonnement' => 'actif']);
        $classe = Classe::factory()->create(['etablissement_id' => $etab->id]);
        $eleve = Etudiant::factory()->create(['etablissement_id' => $etab->id, 'classe_id' => $classe->id]);
        Absence::create(['etudiant_id' => $eleve->id, 'date' => '2026-11-10', 'type' => 'absence', 'justifie' => false]);
        Absence::create(['etudiant_id' => $eleve->id, 'date' => '2026-11-12', 'type' => 'retard', 'justifie' => false]);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'etablissement_id' => $etab->id]));

        $this->getJson("/api/absences?classe_id={$classe->id}&date=2026-11-12")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'retard');

        $this->getJson("/api/absences?classe_id={$classe->id}&date=2026-11-11")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
