<?php

namespace Tests\Feature;

use App\Models\Enseignant;
use App\Models\Etablissement;
use App\Models\Matiere;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Un seul formulaire pour le personnel : fiche enseignant et matières, affectation de la direction et de la vie scolaire. */
class FormulaireUtilisateurUniqueTest extends TestCase
{
    use RefreshDatabase;

    private Etablissement $etab;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->etab = Etablissement::create(['nom' => 'École Une', 'plan' => 'vip', 'statut_abonnement' => 'actif']);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'etablissement_id' => $this->etab->id]));
    }

    public function test_un_compte_enseignant_cree_sa_fiche_avec_ses_matieres(): void
    {
        $maths = Matiere::create(['nom' => 'Mathématiques', 'code' => 'MATH', 'coefficient' => 4, 'etablissement_id' => $this->etab->id]);
        $pc = Matiere::create(['nom' => 'Physique', 'code' => 'PC', 'coefficient' => 3, 'etablissement_id' => $this->etab->id]);
        $autreEcole = Etablissement::create(['nom' => 'Autre', 'plan' => 'vip', 'statut_abonnement' => 'actif']);
        $etrangere = Matiere::withoutGlobalScopes()->create(['nom' => 'SVT', 'code' => 'SVT', 'coefficient' => 2, 'etablissement_id' => $autreEcole->id]);

        $reponse = $this->postJson('/api/users', [
            'name' => 'Fatimé Ali Mahamat', 'email' => 'fatime@ecole.test', 'role' => 'enseignant', 'password' => 'secret123',
            'matieres' => [$maths->id, $pc->id, $etrangere->id], 'specialite' => 'Maths-PC',
        ])->assertCreated();

        $user = User::find($reponse->json('user.id'));
        $fiche = Enseignant::find($user->enseignant_id);
        $this->assertNotNull($fiche);
        $this->assertSame('Fatimé', $fiche->prenom);
        $this->assertSame('Ali Mahamat', $fiche->nom);
        $this->assertSame('Maths-PC', $fiche->specialite);
        $this->assertNotEmpty($fiche->code_pointe);
        // La matière d'un autre établissement est ignorée.
        $this->assertEqualsCanonicalizing([$maths->id, $pc->id], $fiche->matieres()->pluck('matieres.id')->all());
    }

    public function test_modifier_un_enseignant_garde_sa_fiche_et_remplace_ses_matieres(): void
    {
        $maths = Matiere::create(['nom' => 'Mathématiques', 'code' => 'MATH', 'coefficient' => 4, 'etablissement_id' => $this->etab->id]);
        $id = $this->postJson('/api/users', [
            'name' => 'Moussa Hissein', 'email' => 'moussa@ecole.test', 'role' => 'enseignant', 'password' => 'secret123',
        ])->json('user.id');
        $ficheId = User::find($id)->enseignant_id;

        $this->putJson("/api/users/{$id}", [
            'name' => 'Moussa Hissein', 'email' => 'moussa@ecole.test', 'role' => 'enseignant', 'matieres' => [$maths->id],
        ])->assertOk();

        $this->assertSame($ficheId, User::find($id)->enseignant_id);
        $this->assertSame(1, Enseignant::count());
        $this->assertSame([$maths->id], Enseignant::find($ficheId)->matieres()->pluck('matieres.id')->all());
    }

    public function test_censeur_et_surveillant_recoivent_une_affectation(): void
    {
        $censeur = $this->postJson('/api/users', [
            'name' => 'Censeur Lycée', 'email' => 'censeur@ecole.test', 'role' => 'censeur', 'password' => 'secret123', 'affectation' => 'Lycée',
        ])->assertCreated()->json('user.id');
        $surveillant = $this->postJson('/api/users', [
            'name' => 'Surveillant Général', 'email' => 'surv@ecole.test', 'role' => 'surveillant', 'password' => 'secret123',
        ])->assertCreated()->json('user.id');
        $secretaire = $this->postJson('/api/users', [
            'name' => 'Secrétaire', 'email' => 'sec@ecole.test', 'role' => 'secretaire', 'password' => 'secret123', 'affectation' => 'Bâtiment A',
        ])->assertCreated()->json('user.id');

        $this->assertSame('Lycée', User::find($censeur)->affectation);
        $this->assertSame('Général', User::find($surveillant)->affectation);
        $this->assertNull(User::find($secretaire)->affectation);
        $this->assertNull(User::find($secretaire)->enseignant_id);
    }

    public function test_un_surveillant_peut_faire_l_appel_mais_pas_gerer_les_notes(): void
    {
        $surveillant = User::factory()->create(['role' => 'surveillant', 'etablissement_id' => $this->etab->id]);
        Sanctum::actingAs($surveillant);

        $this->getJson('/api/etudiants')->assertOk();
        $this->getJson('/api/absences')->assertOk();
        $this->getJson('/api/users')->assertForbidden();
        $this->postJson('/api/notes', [])->assertForbidden();
    }
}
