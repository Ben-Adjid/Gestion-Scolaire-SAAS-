<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\Enseignant;
use App\Models\Etablissement;
use App\Models\Etudiant;
use App\Models\Matiere;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Cahier de texte (enseignant, parent, direction) et chatboard parent ↔ administration. */
class CahierTexteEtChatboardTest extends TestCase
{
    use RefreshDatabase;

    private Etablissement $etab;
    private Enseignant $prof;
    private User $profUser;
    private Classe $classe;
    private Classe $autreClasse;
    private Matiere $matiere;

    protected function setUp(): void
    {
        parent::setUp();

        $this->etab = Etablissement::create(['nom' => 'Lycée test', 'plan' => 'vip', 'statut_abonnement' => 'actif']);
        $this->prof = Enseignant::factory()->create(['etablissement_id' => $this->etab->id]);
        $this->profUser = User::factory()->create([
            'role' => 'enseignant', 'etablissement_id' => $this->etab->id, 'enseignant_id' => $this->prof->id,
        ]);
        $this->classe = Classe::factory()->create(['etablissement_id' => $this->etab->id]);
        $this->autreClasse = Classe::factory()->create(['etablissement_id' => $this->etab->id]);
        $this->matiere = Matiere::factory()->create(['etablissement_id' => $this->etab->id]);

        // Le professeur enseigne la matière dans la classe principale uniquement.
        \Illuminate\Support\Facades\DB::table('classe_enseignant')->insert([
            'classe_id' => $this->classe->id, 'enseignant_id' => $this->prof->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        \Illuminate\Support\Facades\DB::table('enseignant_matiere')->insert([
            'enseignant_id' => $this->prof->id, 'matiere_id' => $this->matiere->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function entree(array $surcharge = []): array
    {
        return array_merge([
            'classe_id' => $this->classe->id,
            'matiere_id' => $this->matiere->id,
            'date' => '2026-11-03',
            'contenu' => 'Chapitre 4 : les fonctions affines',
            'devoirs' => 'Exercices 1 à 5, page 62',
        ], $surcharge);
    }

    public function test_enseignant_publie_dans_ses_classes(): void
    {
        Sanctum::actingAs($this->profUser);

        $this->postJson('/api/cahier-texte', $this->entree())->assertCreated();
        $this->assertDatabaseHas('cahiers_texte', ['enseignant_id' => $this->prof->id, 'classe_id' => $this->classe->id]);
    }

    public function test_enseignant_refuse_une_classe_non_affectee(): void
    {
        Sanctum::actingAs($this->profUser);

        $this->postJson('/api/cahier-texte', $this->entree(['classe_id' => $this->autreClasse->id]))->assertForbidden();
    }

    public function test_parent_ne_voit_que_les_entrees_de_la_classe_de_son_enfant(): void
    {
        Sanctum::actingAs($this->profUser);
        $this->postJson('/api/cahier-texte', $this->entree())->assertCreated();

        $enfant = Etudiant::factory()->create(['etablissement_id' => $this->etab->id, 'classe_id' => $this->classe->id]);
        $parent = User::factory()->create(['role' => 'parent', 'etablissement_id' => $this->etab->id]);
        $parent->enfants()->attach($enfant->id, ['lien' => 'mere']);

        Sanctum::actingAs($parent);
        $this->getJson('/api/cahier-texte')->assertOk()->assertJsonCount(1);
    }

    public function test_enseignant_ne_modifie_pas_l_entree_d_un_collegue(): void
    {
        Sanctum::actingAs($this->profUser);
        $entree = $this->postJson('/api/cahier-texte', $this->entree())->assertCreated()->json('entree');

        $autreProf = Enseignant::factory()->create(['etablissement_id' => $this->etab->id]);
        $collegue = User::factory()->create(['role' => 'enseignant', 'etablissement_id' => $this->etab->id, 'enseignant_id' => $autreProf->id]);
        Sanctum::actingAs($collegue);

        $this->putJson('/api/cahier-texte/'.$entree['id'], $this->entree(['contenu' => 'Modifié']))->assertForbidden();
        $this->deleteJson('/api/cahier-texte/'.$entree['id'])->assertForbidden();
    }

    public function test_parent_ecrit_a_l_administration_seulement(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'etablissement_id' => $this->etab->id]);
        $parent = User::factory()->create(['role' => 'parent', 'etablissement_id' => $this->etab->id]);
        Sanctum::actingAs($parent);

        $this->postJson('/api/messages', [
            'titre' => 'Absence', 'contenu' => 'Mon enfant est malade.', 'cible' => 'administration',
        ])->assertCreated();
        $this->assertDatabaseHas('messages', ['sender_id' => $parent->id, 'recipient_id' => $admin->id]);

        $this->postJson('/api/messages', [
            'titre' => 'Question', 'contenu' => 'Bonjour', 'recipient_id' => $this->profUser->id,
        ])->assertForbidden();
        $this->assertSame(1, Message::where('sender_id', $parent->id)->count());
    }

    public function test_messagerie_parent_refusee_hors_formule_vip(): void
    {
        $standard = Etablissement::create(['nom' => 'Standard', 'plan' => 'standard', 'statut_abonnement' => 'actif']);
        User::factory()->create(['role' => 'admin', 'etablissement_id' => $standard->id]);
        $parent = User::factory()->create(['role' => 'parent', 'etablissement_id' => $standard->id]);
        Sanctum::actingAs($parent);

        $this->postJson('/api/messages', ['titre' => 'X', 'contenu' => 'Y', 'cible' => 'administration'])
            ->assertForbidden();
    }
}
