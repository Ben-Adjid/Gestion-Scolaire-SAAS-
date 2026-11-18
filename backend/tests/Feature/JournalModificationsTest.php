<?php

namespace Tests\Feature;

use App\Models\Audit;
use App\Models\Classe;
use App\Models\Etablissement;
use App\Models\Etudiant;
use App\Models\FraisScolaire;
use App\Models\Matiere;
use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Journal des modifications : valeurs avant et après pour les notes et les paiements. */
class JournalModificationsTest extends TestCase
{
    use RefreshDatabase;

    private Etablissement $etab;

    protected function setUp(): void
    {
        parent::setUp();
        $this->etab = Etablissement::create(['nom' => 'Journal', 'plan' => 'entreprise', 'statut_abonnement' => 'actif']);
    }

    public function test_une_modification_de_note_garde_la_valeur_avant_et_apres(): void
    {
        $admin = User::factory()->create(['role' => 'proviseur', 'etablissement_id' => $this->etab->id]);
        Sanctum::actingAs($admin);
        $classe = Classe::factory()->create(['etablissement_id' => $this->etab->id]);
        $eleve = Etudiant::factory()->create(['etablissement_id' => $this->etab->id, 'classe_id' => $classe->id]);
        $matiere = Matiere::factory()->create(['etablissement_id' => $this->etab->id]);
        $note = Note::create([
            'etudiant_id' => $eleve->id, 'matiere_id' => $matiere->id, 'trimestre' => 1,
            'type' => 'devoir', 'valeur' => 10, 'statut' => 'brouillon', 'etablissement_id' => $this->etab->id,
        ]);

        $this->putJson("/api/notes/{$note->id}", [
            'etudiant_id' => $eleve->id, 'matiere_id' => $matiere->id, 'trimestre' => 1,
            'type' => 'devoir', 'valeur' => 14, 'statut' => 'brouillon',
        ])->assertOk();

        $audit = Audit::where('module', 'notes')->where('entite_id', $note->id)->where('action', 'modification')->firstOrFail();
        $this->assertEquals(10, $audit->valeurs_avant['valeur']);
        $this->assertEquals(14, $audit->valeurs_apres['valeur']);
        $this->assertSame($admin->id, $audit->user_id);
    }

    public function test_un_paiement_est_journalise_avec_son_montant(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'gestionnaire', 'etablissement_id' => $this->etab->id]));
        $eleve = Etudiant::factory()->create(['etablissement_id' => $this->etab->id]);
        $frais = FraisScolaire::create([
            'etudiant_id' => $eleve->id, 'libelle' => 'Scolarité', 'montant' => 100000,
            'annee_scolaire' => '2026-2027', 'statut' => 'pending', 'etablissement_id' => $this->etab->id,
        ]);

        $this->postJson("/api/frais-scolaires/{$frais->id}/paiements", [
            'montant' => 25000, 'date_paiement' => '2026-11-05', 'mode' => 'especes', 'reference' => 'REC-1',
        ])->assertCreated();

        $audit = Audit::where('module', 'Paiement')->where('action', 'create')->firstOrFail();
        $this->assertEquals(25000, $audit->valeurs_apres['montant']);
        $this->assertSame('REC-1', $audit->valeurs_apres['reference']);
    }

    public function test_le_journal_ne_liste_que_les_modifications_avec_valeurs(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'etablissement_id' => $this->etab->id]);
        Sanctum::actingAs($admin);
        Audit::create(['user_id' => $admin->id, 'etablissement_id' => $this->etab->id, 'action' => 'connexion', 'module' => 'authentification', 'description' => 'sans valeurs']);
        Audit::create([
            'user_id' => $admin->id, 'etablissement_id' => $this->etab->id, 'action' => 'modification', 'module' => 'notes',
            'entite_id' => 7, 'description' => 'avec valeurs', 'valeurs_avant' => ['valeur' => 8], 'valeurs_apres' => ['valeur' => 12],
        ]);

        $descriptions = collect($this->getJson('/api/audits?modifications=1&entite_id=7')->assertOk()->json('data'))->pluck('description');

        $this->assertSame(['avec valeurs'], $descriptions->values()->all());
    }
}
