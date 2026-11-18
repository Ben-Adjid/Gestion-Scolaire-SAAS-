<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\Etablissement;
use App\Models\Etudiant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Le périmètre (cycle ou bâtiment) limite réellement les classes et les élèves visibles. */
class PerimetreAffectationTest extends TestCase
{
    use RefreshDatabase;

    private Etablissement $etab;
    private Classe $sixieme;
    private Classe $terminale;
    private Classe $seconde;

    protected function setUp(): void
    {
        parent::setUp();
        $this->etab = Etablissement::create(['nom' => 'Complexe', 'plan' => 'vip', 'statut_abonnement' => 'actif']);
        $this->sixieme = Classe::create(['nom' => '6e A', 'niveau' => '6ème', 'batiment' => 'Bâtiment A', 'etablissement_id' => $this->etab->id]);
        $this->terminale = Classe::create(['nom' => 'Tle D', 'niveau' => 'Terminale', 'batiment' => 'Bâtiment B', 'etablissement_id' => $this->etab->id]);
        $this->seconde = Classe::create(['nom' => '2nde C', 'niveau' => 'Seconde', 'batiment' => 'Bâtiment A', 'etablissement_id' => $this->etab->id]);
        foreach ([$this->sixieme, $this->terminale, $this->seconde] as $classe) {
            Etudiant::factory()->create(['etablissement_id' => $this->etab->id, 'classe_id' => $classe->id]);
        }
    }

    private function compte(string $role, ?string $affectation): User
    {
        $user = User::factory()->create(['role' => $role, 'affectation' => $affectation, 'etablissement_id' => $this->etab->id]);
        Sanctum::actingAs($user);

        return $user;
    }

    private function idsClasses(): array
    {
        return collect($this->getJson('/api/classes?per_page=100')->assertOk()->json('data'))->pluck('id')->sort()->values()->all();
    }

    public function test_le_cycle_est_deduit_du_niveau(): void
    {
        $this->assertSame('college', $this->sixieme->cycle);
        $this->assertSame('lycee', $this->terminale->cycle);
    }

    public function test_censeur_du_lycee_ne_voit_que_le_lycee(): void
    {
        $this->compte('censeur', 'Lycée');

        $this->assertSame([$this->terminale->id, $this->seconde->id], $this->idsClasses());
        $this->assertCount(2, $this->getJson('/api/etudiants?per_page=100')->json('data'));
        $eleveCollege = Etudiant::where('classe_id', $this->sixieme->id)->first();
        $this->getJson("/api/etudiants/{$eleveCollege->id}")->assertForbidden();
        $this->getJson("/api/classes/{$this->sixieme->id}/bulletins")->assertForbidden();
    }

    public function test_surveillant_du_batiment_a_fait_l_appel_seulement_dans_son_batiment(): void
    {
        $this->compte('surveillant', 'Bâtiment A');

        $this->assertSame([$this->sixieme->id, $this->seconde->id], $this->idsClasses());
        $this->getJson("/api/appels?classe_id={$this->terminale->id}&date=".now()->toDateString())->assertForbidden();
        $this->getJson("/api/appels?classe_id={$this->seconde->id}&date=".now()->toDateString())->assertOk();
    }

    public function test_general_et_admin_voient_tout_et_un_perimetre_inconnu_ne_voit_rien(): void
    {
        $this->compte('cpe', 'Général');
        $this->assertCount(3, $this->idsClasses());

        $this->compte('admin', 'Lycée'); // l'administrateur n'a jamais de périmètre
        $this->assertCount(3, $this->idsClasses());

        $this->compte('censeur', 'Annexe Z');
        $this->assertSame([], $this->idsClasses());
        $this->assertCount(0, $this->getJson('/api/etudiants?per_page=100')->json('data'));
    }

    public function test_creer_une_classe_depuis_le_formulaire_sans_cycle_ni_effectif(): void
    {
        $this->compte('admin', null);

        $id = $this->postJson('/api/classes', ['nom' => '4e B', 'niveau' => '4ème', 'description' => '', 'batiment' => ' Bâtiment C '])
            ->assertCreated()->json('classe.id');
        $classe = Classe::find($id);
        $this->assertSame('college', $classe->cycle);
        $this->assertSame('Bâtiment C', $classe->batiment);
        $this->assertSame(35, (int) $classe->effectif_max);
    }
}
