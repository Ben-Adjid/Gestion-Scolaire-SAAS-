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

/** Réenregistrer un appel ne doit pas effacer la justification d'une absence. */
class AppelJustificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_absence_justifiee_garde_sa_justification_au_reenregistrement(): void
    {
        $etab = Etablissement::create(['nom' => 'Justif', 'plan' => 'vip', 'statut_abonnement' => 'actif']);
        $classe = Classe::factory()->create(['etablissement_id' => $etab->id]);
        $eleve = Etudiant::factory()->create(['etablissement_id' => $etab->id, 'classe_id' => $classe->id]);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'etablissement_id' => $etab->id]));

        $date = '2026-11-04';
        $appel = fn (string $statut) => $this->postJson('/api/appels', [
            'classe_id' => $classe->id,
            'date' => $date,
            'presences' => [['etudiant_id' => $eleve->id, 'statut' => $statut]],
        ])->assertOk();

        $appel('absence');
        $absence = Absence::withoutGlobalScopes()->where('etudiant_id', $eleve->id)->firstOrFail();
        $absence->update(['justifie' => true]);

        // Nouvel enregistrement de la même journée, toujours absent : la justification reste.
        $appel('absence');

        $this->assertTrue(Absence::withoutGlobalScopes()->find($absence->id)->justifie);
        $this->assertSame(1, Absence::withoutGlobalScopes()->where('etudiant_id', $eleve->id)->count());
    }
}
