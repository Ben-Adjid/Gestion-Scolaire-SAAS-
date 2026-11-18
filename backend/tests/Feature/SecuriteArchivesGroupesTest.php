<?php

namespace Tests\Feature;

use App\Models\ArchiveAnnee;
use App\Models\Etablissement;
use App\Models\Etudiant;
use App\Models\User;
use App\Services\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Double authentification, archives scellées, multi-sites, restriction de l'assistant. */
class SecuriteArchivesGroupesTest extends TestCase
{
    use RefreshDatabase;

    private function etablissement(string $plan, array $surcharge = []): Etablissement
    {
        return Etablissement::create(array_merge(['nom' => "Lycée {$plan}", 'plan' => $plan, 'statut_abonnement' => 'actif'], $surcharge));
    }

    public function test_totp_respecte_le_vecteur_rfc6238(): void
    {
        // Secret RFC 6238 « 12345678901234567890 » encodé en base32, instant 59 s : code attendu 287082 (SHA-1, 8 chiffres -> 6).
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        $this->assertSame('287082', Totp::code($secret, 59));
    }

    public function test_double_authentification_active_puis_exige_le_code(): void
    {
        $etab = $this->etablissement('entreprise');
        $admin = User::factory()->create(['role' => 'admin', 'etablissement_id' => $etab->id, 'password' => bcrypt('motdepasse')]);
        Sanctum::actingAs($admin);

        $secret = $this->postJson('/api/2fa/activer')->assertOk()->json('secret');
        $this->postJson('/api/2fa/confirmer', ['code' => '000000'])->assertUnprocessable();
        $this->postJson('/api/2fa/confirmer', ['code' => Totp::code($secret)])->assertOk();
        $this->assertTrue((bool) $admin->fresh()->two_factor_confirmed_at);

        // Connexion sans code : le serveur demande le code.
        $this->postJson('/api/login', ['email' => $admin->email, 'password' => 'motdepasse'])
            ->assertStatus(202)->assertJsonPath('requires_2fa', true);

        // Code invalide : refusé.
        $this->postJson('/api/login', ['email' => $admin->email, 'password' => 'motdepasse', 'code' => '111111'])
            ->assertUnprocessable();

        // Code valide : connexion acceptée.
        $this->postJson('/api/login', ['email' => $admin->email, 'password' => 'motdepasse', 'code' => Totp::code($secret)])
            ->assertOk()->assertJsonStructure(['token']);
    }

    public function test_archive_scellee_detecte_toute_alteration(): void
    {
        $etab = $this->etablissement('entreprise');
        Etudiant::factory()->create(['etablissement_id' => $etab->id, 'nom' => 'Archive']);
        $admin = User::factory()->create(['role' => 'admin', 'etablissement_id' => $etab->id]);
        Sanctum::actingAs($admin);

        $this->postJson('/api/archives', ['annee_scolaire' => '2025-2026'])->assertCreated();
        $this->postJson('/api/archives', ['annee_scolaire' => '2025-2026'])->assertStatus(409);

        $archive = ArchiveAnnee::first();
        $this->getJson("/api/archives/{$archive->id}/verifier")->assertOk()->assertJsonPath('intacte', true);

        // Altération directe en base : la vérification doit le signaler.
        $archive->forceFill(['donnees' => str_replace('Archive', 'Falsifie', $archive->donnees)])->save();
        $this->getJson("/api/archives/{$archive->id}/verifier")->assertOk()->assertJsonPath('intacte', false);
    }

    public function test_archivage_reserve_a_entreprise(): void
    {
        $vip = $this->etablissement('vip');
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'etablissement_id' => $vip->id]));

        $this->postJson('/api/archives', ['annee_scolaire' => '2024-2025'])->assertForbidden();
    }

    public function test_synthese_multi_sites_donne_des_totaux_sans_detail_des_autres_sites(): void
    {
        $groupe = \App\Models\GroupeScolaire::create(['nom' => 'Groupe Test']);
        $siteA = $this->etablissement('entreprise', ['groupe_scolaire_id' => $groupe->id, 'nom' => 'Site A']);
        $siteB = $this->etablissement('entreprise', ['groupe_scolaire_id' => $groupe->id, 'nom' => 'Site B']);
        $horsGroupe = $this->etablissement('entreprise', ['nom' => 'Hors groupe']);
        Etudiant::factory()->count(2)->create(['etablissement_id' => $siteA->id]);
        Etudiant::factory()->count(3)->create(['etablissement_id' => $siteB->id]);
        Etudiant::factory()->count(5)->create(['etablissement_id' => $horsGroupe->id]);

        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'etablissement_id' => $siteA->id]));
        $synthese = $this->getJson('/api/groupe/synthese')->assertOk()->json();

        $this->assertSame('Groupe Test', $synthese['groupe']);
        $this->assertSame(2, $synthese['totaux']['sites']);
        $this->assertSame(5, $synthese['totaux']['eleves']);
        $this->assertNotContains('Hors groupe', array_column($synthese['sites'], 'nom'));
    }

    public function test_assistant_ne_donne_pas_les_statistiques_aux_gestionnaires(): void
    {
        $etab = $this->etablissement('vip');
        Sanctum::actingAs(User::factory()->create(['role' => 'gestionnaire', 'etablissement_id' => $etab->id]));

        $reponse = $this->postJson('/api/assistant', ['message' => 'statistiques des élèves'])->assertOk()->json('reply');
        $this->assertStringContainsString("n'est pas accessible", $reponse);
    }

    public function test_calendrier_lisible_par_tous_et_saisi_par_la_direction(): void
    {
        $etab = $this->etablissement('standard');
        Sanctum::actingAs(User::factory()->create(['role' => 'secretaire', 'etablissement_id' => $etab->id]));
        $this->getJson('/api/calendrier')->assertOk();
        $this->postJson('/api/calendrier', ['titre' => 'Rentrée', 'type' => 'rentree', 'date_debut' => '2026-10-01'])->assertForbidden();

        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'etablissement_id' => $etab->id]));
        $this->postJson('/api/calendrier', ['titre' => 'Rentrée', 'type' => 'rentree', 'date_debut' => '2026-10-01', 'date_fin' => '2026-10-02'])
            ->assertCreated();
        $this->getJson('/api/calendrier')->assertOk()->assertJsonCount(1);
    }
}
