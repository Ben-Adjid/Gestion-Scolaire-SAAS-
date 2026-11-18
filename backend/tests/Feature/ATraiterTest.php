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

/** « À traiter aujourd'hui » : compte les actions en attente de la direction, par établissement. */
class ATraiterTest extends TestCase
{
    use RefreshDatabase;

    public function test_compte_les_absences_non_justifiees_de_son_etablissement_seulement(): void
    {
        $mon = Etablissement::create(['nom' => 'Moi', 'plan' => 'standard', 'statut_abonnement' => 'actif']);
        $autre = Etablissement::create(['nom' => 'Autre', 'plan' => 'standard', 'statut_abonnement' => 'actif']);
        Sanctum::actingAs(User::factory()->create(['role' => 'proviseur', 'etablissement_id' => $mon->id]));

        $eleve = Etudiant::factory()->create(['etablissement_id' => $mon->id, 'classe_id' => Classe::factory()->create(['etablissement_id' => $mon->id])->id]);
        $eleveAutre = Etudiant::factory()->create(['etablissement_id' => $autre->id, 'classe_id' => Classe::factory()->create(['etablissement_id' => $autre->id])->id]);
        Absence::create(['etudiant_id' => $eleve->id, 'date' => now()->toDateString(), 'type' => 'absence', 'justifie' => false]);
        Absence::create(['etudiant_id' => $eleveAutre->id, 'date' => now()->toDateString(), 'type' => 'absence', 'justifie' => false]);

        $this->getJson('/api/a-traiter')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('taches.0.nombre', 1);
    }

    public function test_un_enseignant_n_a_pas_acces_a_la_liste(): void
    {
        $etab = Etablissement::create(['nom' => 'Prof', 'plan' => 'standard', 'statut_abonnement' => 'actif']);
        Sanctum::actingAs(User::factory()->create(['role' => 'enseignant', 'etablissement_id' => $etab->id]));

        $this->getJson('/api/a-traiter')->assertForbidden();
    }
}
