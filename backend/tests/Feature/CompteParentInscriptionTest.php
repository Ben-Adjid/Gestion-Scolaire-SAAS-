<?php

namespace Tests\Feature;

use App\Mail\CompteCree;
use App\Models\Classe;
use App\Models\Etablissement;
use App\Models\Etudiant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Compte parent créé automatiquement à l'inscription, sans doublon (fratrie, réinscription). */
class CompteParentInscriptionTest extends TestCase
{
    use RefreshDatabase;

    private Etablissement $etab;
    private Classe $classe;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->etab = Etablissement::create(['nom' => 'École Familles', 'plan' => 'vip', 'statut_abonnement' => 'actif']);
        $this->classe = Classe::factory()->create(['etablissement_id' => $this->etab->id]);
        Sanctum::actingAs(User::factory()->create(['role' => 'secretaire', 'etablissement_id' => $this->etab->id]));
    }

    private function inscrire(array $eleve, ?array $parent): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/inscriptions', $eleve + [
            'classe_id' => $this->classe->id, 'annee_scolaire' => '2026-2027',
            'date_inscription' => now()->toDateString(), 'statut' => 'active', 'type' => 'nouveau',
        ] + ($parent ? ['parent' => $parent] : []));
    }

    public function test_l_inscription_cree_le_compte_parent_avec_mot_de_passe_provisoire(): void
    {
        $reponse = $this->inscrire(['nom' => 'Mahamat', 'prenom' => 'Ali'], [
            'nom' => 'Hawa Mahamat', 'email' => 'Hawa@Famille.test', 'telephone' => '+235 66 12 34 56', 'lien' => 'mere',
        ])->assertCreated()->assertJsonPath('compte_parent.statut', 'cree');

        $mdp = $reponse->json('compte_parent.mot_de_passe_provisoire');
        $this->assertSame(10, strlen($mdp));

        $parent = User::where('email', 'hawa@famille.test')->firstOrFail();
        $this->assertSame('parent', $parent->role);
        $this->assertSame($this->etab->id, $parent->etablissement_id);
        $this->assertTrue((bool) $parent->doit_changer_mot_de_passe);
        $this->assertTrue(Hash::check($mdp, $parent->password));
        $this->assertSame('mere', $parent->enfants()->first()->pivot->lien);
        Mail::assertSent(CompteCree::class, fn ($m) => $m->hasTo('hawa@famille.test') && ! str_contains($m->render(), $mdp));
    }

    public function test_un_frere_est_relie_au_meme_compte_par_email_ou_telephone(): void
    {
        $this->inscrire(['nom' => 'Mahamat', 'prenom' => 'Ali'], ['nom' => 'Hawa Mahamat', 'email' => 'hawa@famille.test', 'telephone' => '+235 66 12 34 56']);

        $this->inscrire(['nom' => 'Mahamat', 'prenom' => 'Zara'], ['nom' => 'Hawa Mahamat', 'email' => 'HAWA@famille.test'])
            ->assertCreated()->assertJsonPath('compte_parent.statut', 'relie')->assertJsonPath('compte_parent.mot_de_passe_provisoire', null);
        // Sans e-mail, le téléphone suffit (format différent, mêmes chiffres).
        $this->inscrire(['nom' => 'Mahamat', 'prenom' => 'Idriss'], ['nom' => 'Hawa', 'telephone' => '66123456'])
            ->assertCreated()->assertJsonPath('compte_parent.statut', 'relie');

        $this->assertSame(1, User::where('role', 'parent')->count());
        $this->assertSame(3, User::where('role', 'parent')->first()->enfants()->count());
    }

    public function test_la_reinscription_garde_le_meme_parent(): void
    {
        $this->inscrire(['nom' => 'Mahamat', 'prenom' => 'Ali'], ['nom' => 'Hawa Mahamat', 'email' => 'hawa@famille.test']);
        $eleve = Etudiant::where('prenom', 'Ali')->firstOrFail();

        $this->postJson('/api/inscriptions', [
            'etudiant_id' => $eleve->id, 'classe_id' => $this->classe->id, 'annee_scolaire' => '2027-2028',
            'date_inscription' => now()->toDateString(), 'statut' => 'active', 'type' => 'ancien',
            'parent' => ['nom' => 'Hawa Mahamat', 'email' => 'hawa@famille.test'],
        ])->assertCreated()->assertJsonPath('compte_parent.statut', 'deja_lie');

        $this->assertSame(1, User::where('role', 'parent')->count());
    }

    public function test_erreurs_annulent_l_inscription(): void
    {
        // E-mail d'un membre du personnel : refusé, et l'élève n'est pas créé.
        $this->inscrire(['nom' => 'Test', 'prenom' => 'Un'], ['nom' => 'Quelqu’un', 'email' => auth()->user()->email])
            ->assertStatus(422)->assertJsonValidationErrors('parent.email');
        // Nouveau parent sans e-mail : impossible de lui créer un identifiant.
        $this->inscrire(['nom' => 'Test', 'prenom' => 'Deux'], ['nom' => 'Sans Mail', 'telephone' => '99 88 77 66'])
            ->assertStatus(422)->assertJsonValidationErrors('parent.email');

        $this->assertSame(0, Etudiant::count());
    }

    public function test_un_parent_d_une_autre_ecole_n_est_pas_relie(): void
    {
        $autre = Etablissement::create(['nom' => 'Autre', 'plan' => 'vip', 'statut_abonnement' => 'actif']);
        User::factory()->create(['role' => 'parent', 'email' => 'parent@ailleurs.test', 'telephone' => '+235 66 12 34 56', 'etablissement_id' => $autre->id]);

        $this->inscrire(['nom' => 'X', 'prenom' => 'Y'], ['nom' => 'Parent', 'email' => 'parent@ailleurs.test'])
            ->assertStatus(422)->assertJsonValidationErrors('parent.email');
        // Même téléphone qu'un parent d'une autre école : un nouveau compte est créé dans cette école.
        $this->inscrire(['nom' => 'X', 'prenom' => 'Z'], ['nom' => 'Parent', 'email' => 'nouveau@ici.test', 'telephone' => '66123456'])
            ->assertCreated()->assertJsonPath('compte_parent.statut', 'cree');
    }

    public function test_formule_standard_sans_espace_parent_ignore_le_compte(): void
    {
        $this->etab->update(['plan' => 'standard']);
        $this->inscrire(['nom' => 'Std', 'prenom' => 'Eleve'], ['nom' => 'Parent Std', 'email' => 'std@famille.test'])
            ->assertCreated()->assertJsonPath('compte_parent', null);
        $this->assertSame(0, User::where('role', 'parent')->count());
    }

    public function test_compte_parent_pour_un_eleve_deja_inscrit_et_creation_eleve_directe(): void
    {
        $eleve = Etudiant::factory()->create(['etablissement_id' => $this->etab->id, 'classe_id' => $this->classe->id]);
        $this->postJson("/api/etudiants/{$eleve->id}/compte-parent", ['parent' => ['nom' => 'Moussa Abakar', 'email' => 'moussa@famille.test', 'lien' => 'pere']])
            ->assertCreated()->assertJsonPath('compte_parent.statut', 'cree');
        $this->getJson("/api/etudiants/{$eleve->id}/parents")->assertOk()->assertJsonPath('0.lien', 'pere');

        $this->postJson('/api/etudiants', [
            'nom' => 'Abakar', 'prenom' => 'Fatimé', 'date_naissance' => '2012-03-04', 'genre' => 'F',
            'classe_id' => $this->classe->id, 'annee_scolaire' => '2026-2027',
            'parent' => ['nom' => 'Moussa Abakar', 'email' => 'moussa@famille.test'],
        ])->assertCreated()->assertJsonPath('compte_parent.statut', 'relie');
    }
}
