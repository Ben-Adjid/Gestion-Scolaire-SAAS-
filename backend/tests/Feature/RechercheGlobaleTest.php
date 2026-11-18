<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\Etablissement;
use App\Models\Etudiant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Recherche globale : résultats limités à l'établissement et au périmètre de l'utilisateur. */
class RechercheGlobaleTest extends TestCase
{
    use RefreshDatabase;

    private Etablissement $etab;

    protected function setUp(): void
    {
        parent::setUp();
        $this->etab = Etablissement::create(['nom' => 'Recherche', 'plan' => 'standard', 'statut_abonnement' => 'actif']);
    }

    public function test_l_admin_trouve_eleves_et_classes_de_son_etablissement(): void
    {
        $classe = Classe::factory()->create(['etablissement_id' => $this->etab->id, 'nom' => 'Terminale Zeta']);
        Etudiant::factory()->create(['etablissement_id' => $this->etab->id, 'classe_id' => $classe->id, 'nom' => 'Zerbo', 'prenom' => 'Aminata']);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'etablissement_id' => $this->etab->id]));

        $this->getJson('/api/recherche?q=zerbo')
            ->assertOk()
            ->assertJsonPath('eleves.0.libelle', 'Aminata Zerbo');

        $this->getJson('/api/recherche?q=zeta')
            ->assertOk()
            ->assertJsonPath('classes.0.libelle', 'Terminale Zeta');
    }

    public function test_une_recherche_trop_courte_ne_renvoie_rien(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'etablissement_id' => $this->etab->id]));

        $this->getJson('/api/recherche?q=a')
            ->assertOk()
            ->assertJsonPath('eleves', [])
            ->assertJsonPath('classes', []);
    }

    public function test_un_enseignant_ne_voit_que_les_eleves_de_ses_classes(): void
    {
        $enseignant = \App\Models\Enseignant::factory()->create(['etablissement_id' => $this->etab->id]);
        $mienne = Classe::factory()->create(['etablissement_id' => $this->etab->id]);
        $autre = Classe::factory()->create(['etablissement_id' => $this->etab->id]);
        \Illuminate\Support\Facades\DB::table('classe_enseignant')->insert([
            'classe_id' => $mienne->id, 'enseignant_id' => $enseignant->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        Etudiant::factory()->create(['etablissement_id' => $this->etab->id, 'classe_id' => $mienne->id, 'nom' => 'Ouedraogo', 'prenom' => 'Sali']);
        Etudiant::factory()->create(['etablissement_id' => $this->etab->id, 'classe_id' => $autre->id, 'nom' => 'Ouedraogo', 'prenom' => 'Paul']);

        Sanctum::actingAs(User::factory()->create([
            'role' => 'enseignant', 'etablissement_id' => $this->etab->id, 'enseignant_id' => $enseignant->id,
        ]));

        $res = $this->getJson('/api/recherche?q=ouedraogo')->assertOk();
        $this->assertCount(1, $res->json('eleves'));
        $this->assertSame('Sali Ouedraogo', $res->json('eleves.0.libelle'));
    }

    public function test_le_terme_de_recherche_n_est_pas_interprete_comme_un_joker(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'etablissement_id' => $this->etab->id]));

        // « % » seul ne doit pas correspondre à tous les élèves.
        $this->getJson('/api/recherche?q=%25%25')->assertOk()->assertJsonPath('eleves', []);
    }
}
