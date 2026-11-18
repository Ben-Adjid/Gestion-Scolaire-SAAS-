<?php

namespace Tests\Feature;

use App\Models\Etablissement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Bulletins personnalisables : signataire, fonction, mention, rang, couleur, par établissement. */
class BulletinPersonnaliseTest extends TestCase
{
    use RefreshDatabase;

    public function test_l_admin_enregistre_les_reglages_de_bulletin(): void
    {
        $etab = Etablissement::create(['nom' => 'Bulletin', 'plan' => 'vip', 'statut_abonnement' => 'actif', 'annee_scolaire' => '2026-2027']);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'etablissement_id' => $etab->id]));

        $this->postJson('/api/etablissement', [
            'nom' => 'Bulletin', 'annee_scolaire' => '2026-2027',
            'bulletin_signature_nom' => 'Awa Diop', 'bulletin_signature_titre' => 'Le Proviseur',
            'bulletin_mention' => 'Bulletin confidentiel', 'bulletin_afficher_rang' => false, 'bulletin_couleur' => '#1F5C4A',
        ])->assertOk();

        $etab->refresh();
        $this->assertSame('Awa Diop', $etab->bulletin_signature_nom);
        $this->assertFalse($etab->bulletin_afficher_rang);
        $this->assertSame('#1F5C4A', $etab->bulletin_couleur);
    }

    public function test_une_couleur_invalide_est_refusee(): void
    {
        $etab = Etablissement::create(['nom' => 'Couleur', 'plan' => 'vip', 'statut_abonnement' => 'actif', 'annee_scolaire' => '2026-2027']);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'etablissement_id' => $etab->id]));

        $this->postJson('/api/etablissement', ['nom' => 'Couleur', 'annee_scolaire' => '2026-2027', 'bulletin_couleur' => 'rouge'])
            ->assertUnprocessable();
    }

    public function test_le_rang_est_affiche_par_defaut(): void
    {
        $etab = Etablissement::create(['nom' => 'Defaut', 'plan' => 'vip', 'statut_abonnement' => 'actif']);

        $this->assertTrue($etab->fresh()->bulletin_afficher_rang);
    }
}
