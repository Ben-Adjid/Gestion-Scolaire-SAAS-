<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\Etablissement;
use App\Models\Etudiant;
use App\Models\Inscription;
use App\Models\Matiere;
use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Reprises : moyenne de l'année de référence, modification et suppression des inscriptions, élèves et classes. */
class ReprisesModificationSuppressionTest extends TestCase
{
    use RefreshDatabase;

    private Etablissement $etab;
    private Classe $troisieme;
    private Classe $seconde;

    protected function setUp(): void
    {
        parent::setUp();
        $this->etab = Etablissement::create(['nom' => 'École Reprises', 'plan' => 'vip', 'statut_abonnement' => 'actif', 'annee_scolaire' => '2026-2027']);
        $this->troisieme = Classe::create(['nom' => '3e A', 'niveau' => '3ème', 'etablissement_id' => $this->etab->id]);
        $this->seconde = Classe::create(['nom' => '2nde A', 'niveau' => 'Seconde', 'etablissement_id' => $this->etab->id]);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'etablissement_id' => $this->etab->id]));
    }

    private function eleve(string $anneeFiche = '2026-2027'): Etudiant
    {
        return Etudiant::factory()->create(['etablissement_id' => $this->etab->id, 'classe_id' => $this->troisieme->id, 'annee_scolaire' => $anneeFiche]);
    }

    private function note(Etudiant $eleve, float $valeur, string $annee, string $statut = 'valide'): void
    {
        $matiere = Matiere::firstOrCreate(['code' => 'M', 'etablissement_id' => $this->etab->id], ['nom' => 'Maths', 'coefficient' => 1]);
        Note::create(['etudiant_id' => $eleve->id, 'matiere_id' => $matiere->id, 'valeur' => $valeur, 'trimestre' => 1, 'type' => 'devoir', 'statut' => $statut, 'annee_scolaire' => $annee]);
    }

    public function test_la_moyenne_ne_prend_que_l_annee_de_reference(): void
    {
        $eleve = $this->eleve('2025-2026');
        Inscription::create(['etudiant_id' => $eleve->id, 'classe_id' => $this->troisieme->id, 'annee_scolaire' => '2025-2026', 'type' => 'nouveau', 'date_inscription' => '2025-09-01', 'statut' => 'active', 'etablissement_id' => $this->etab->id]);
        $this->note($eleve, 14, '2025-2026');
        $this->note($eleve, 4, '2024-2025'); // ancienne année : ignorée

        $this->getJson('/api/etudiants/lookup?matricule='.$eleve->matricule)
            ->assertOk()->assertJsonPath('annee_reference', '2025-2026')->assertJsonPath('moyenne_annuelle', 14);
    }

    public function test_une_note_prend_l_annee_en_cours_de_l_ecole(): void
    {
        $eleve = $this->eleve();
        $this->note($eleve, 12, '');
        $this->assertSame('2026-2027', Note::where('etudiant_id', $eleve->id)->value('annee_scolaire'));
    }

    public function test_modifier_une_inscription_respecte_la_regle_de_passage(): void
    {
        $eleve = $this->eleve();
        $inscription = Inscription::create(['etudiant_id' => $eleve->id, 'classe_id' => $this->troisieme->id, 'annee_scolaire' => '2026-2027', 'type' => 'nouveau', 'date_inscription' => '2026-09-01', 'statut' => 'active', 'etablissement_id' => $this->etab->id]);

        // Passage en seconde sans moyenne : refusé.
        $this->putJson("/api/inscriptions/{$inscription->id}", ['classe_id' => $this->seconde->id, 'date_inscription' => '2026-09-02', 'statut' => 'active'])
            ->assertStatus(422)->assertJsonValidationErrors('classe_id');

        // Modification sans changement de classe : acceptée.
        $this->putJson("/api/inscriptions/{$inscription->id}", ['classe_id' => $this->troisieme->id, 'date_inscription' => '2026-09-05', 'statut' => 'terminee'])
            ->assertOk()->assertJsonPath('inscription.statut', 'terminee');
    }

    public function test_les_inscriptions_des_annees_passees_sont_figees(): void
    {
        $eleve = $this->eleve('2025-2026');
        $ancienne = Inscription::create(['etudiant_id' => $eleve->id, 'classe_id' => $this->troisieme->id, 'annee_scolaire' => '2025-2026', 'type' => 'nouveau', 'date_inscription' => '2025-09-01', 'statut' => 'active', 'etablissement_id' => $this->etab->id]);

        $this->putJson("/api/inscriptions/{$ancienne->id}", ['classe_id' => $this->troisieme->id, 'date_inscription' => '2025-09-02', 'statut' => 'annulee'])
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'figées') || str_contains($m, 'ne sont plus'));
        $this->deleteJson("/api/inscriptions/{$ancienne->id}")->assertStatus(422);
        $this->assertNotNull(Inscription::find($ancienne->id));
    }

    public function test_supprimer_une_inscription_sans_notes_ni_paiements(): void
    {
        $eleve = $this->eleve();
        $inscription = Inscription::create(['etudiant_id' => $eleve->id, 'classe_id' => $this->troisieme->id, 'annee_scolaire' => '2026-2027', 'type' => 'nouveau', 'date_inscription' => '2026-09-01', 'statut' => 'active', 'etablissement_id' => $this->etab->id]);
        $this->deleteJson("/api/inscriptions/{$inscription->id}")->assertOk();
        $this->assertNull(Inscription::find($inscription->id));

        $avecNotes = Inscription::create(['etudiant_id' => $eleve->id, 'classe_id' => $this->troisieme->id, 'annee_scolaire' => '2026-2027', 'type' => 'nouveau', 'date_inscription' => '2026-09-01', 'statut' => 'active', 'etablissement_id' => $this->etab->id]);
        $this->note($eleve, 10, '2026-2027');
        $this->deleteJson("/api/inscriptions/{$avecNotes->id}")->assertStatus(422);
    }

    public function test_un_eleve_avec_historique_ne_se_supprime_pas(): void
    {
        $avecNotes = $this->eleve();
        $this->note($avecNotes, 11, '2026-2027');
        $this->deleteJson("/api/etudiants/{$avecNotes->id}")->assertStatus(422);
        $this->assertNotNull(Etudiant::find($avecNotes->id));

        $vierge = $this->eleve();
        $this->deleteJson("/api/etudiants/{$vierge->id}")->assertNoContent();
    }

    public function test_le_matricule_ne_change_pas_a_la_modification(): void
    {
        $eleve = $this->eleve();
        $matricule = $eleve->matricule;
        $this->putJson("/api/etudiants/{$eleve->id}", [
            'nom' => 'Nouveau', 'prenom' => 'Nom', 'date_naissance' => '2012-01-01', 'genre' => 'M', 'matricule' => 'AUTRE-999', 'classe_id' => $this->troisieme->id,
        ])->assertOk();
        $this->assertSame($matricule, $eleve->fresh()->matricule);
    }

    public function test_une_classe_avec_des_eleves_ne_se_supprime_pas_ni_ne_change_de_niveau(): void
    {
        $this->eleve();
        $this->deleteJson("/api/classes/{$this->troisieme->id}")->assertStatus(422);
        $this->putJson("/api/classes/{$this->troisieme->id}", ['nom' => '3e A', 'niveau' => 'Seconde', 'cycle' => 'lycee', 'effectif_max' => 35])
            ->assertStatus(422)->assertJsonValidationErrors('niveau');

        $vide = Classe::create(['nom' => 'Vide', 'niveau' => '4ème', 'etablissement_id' => $this->etab->id]);
        $this->deleteJson("/api/classes/{$vide->id}")->assertOk();
    }

    public function test_la_liste_des_eleves_signale_ceux_qui_ont_un_historique(): void
    {
        $avecNotes = $this->eleve();
        $this->note($avecNotes, 9, '2026-2027');
        $vierge = $this->eleve();

        $data = collect($this->getJson('/api/etudiants?per_page=50')->assertOk()->json('data'));
        $this->assertTrue((bool) $data->firstWhere('id', $avecNotes->id)['a_notes_validees']);
        $this->assertFalse((bool) $data->firstWhere('id', $vierge->id)['a_notes_validees']);
    }
}
