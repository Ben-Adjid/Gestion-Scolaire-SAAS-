<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\Etablissement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Chaque rôle peut faire son métier, et seulement celui-là. */
class RolesMetiersTest extends TestCase
{
    use RefreshDatabase;

    private Etablissement $etab;

    protected function setUp(): void
    {
        parent::setUp();
        $this->etab = Etablissement::create(['nom' => 'Lycée métiers', 'plan' => 'vip', 'statut_abonnement' => 'actif']);
    }

    private function compte(string $role): User
    {
        return User::factory()->create(['role' => $role, 'etablissement_id' => $this->etab->id]);
    }

    private function charge(string $role = 'secretaire'): array
    {
        return ['name' => 'Nouveau', 'email' => uniqid('u').'@test.com', 'role' => $role, 'password' => 'secret123'];
    }

    public function test_proviseur_cree_le_personnel_mais_pas_les_comptes_de_direction(): void
    {
        Sanctum::actingAs($this->compte('proviseur'));

        $this->postJson('/api/users', $this->charge('enseignant'))->assertCreated();
        $this->postJson('/api/users', $this->charge('admin'))->assertUnprocessable();
        $this->postJson('/api/users', $this->charge('proviseur'))->assertUnprocessable();
    }

    public function test_proviseur_ne_modifie_pas_un_compte_admin(): void
    {
        $admin = $this->compte('admin');
        Sanctum::actingAs($this->compte('proviseur'));

        // Le rôle demandé est un rôle attribuable par un proviseur : c'est la cible (compte admin) qui est protégée.
        $this->putJson("/api/users/{$admin->id}", ['name' => 'Piraté', 'email' => $admin->email, 'role' => 'enseignant'])
            ->assertForbidden();
        $this->deleteJson("/api/users/{$admin->id}")->assertForbidden();
    }

    public function test_enseignant_fait_l_appel_de_ses_classes(): void
    {
        $prof = \App\Models\Enseignant::factory()->create(['etablissement_id' => $this->etab->id]);
        $user = User::factory()->create(['role' => 'enseignant', 'etablissement_id' => $this->etab->id, 'enseignant_id' => $prof->id]);
        $classe = Classe::factory()->create(['etablissement_id' => $this->etab->id]);
        \Illuminate\Support\Facades\DB::table('classe_enseignant')->insert([
            'classe_id' => $classe->id, 'enseignant_id' => $prof->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/appels?classe_id='.$classe->id.'&date='.today()->toDateString())->assertOk();
        $autre = Classe::factory()->create(['etablissement_id' => $this->etab->id]);
        $this->getJson('/api/appels?classe_id='.$autre->id.'&date='.today()->toDateString())->assertForbidden();
    }
}
