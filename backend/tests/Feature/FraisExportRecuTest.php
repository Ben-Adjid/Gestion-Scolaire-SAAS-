<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\Etudiant;
use App\Models\FraisScolaire;
use App\Models\Paiement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FraisExportRecuTest extends TestCase
{
    use RefreshDatabase;

    private function fraisAvecPaiement(): array
    {
        $gestionnaire = User::factory()->create(['role' => 'gestionnaire']);
        $eleve = Etudiant::factory()->create(['classe_id' => Classe::factory()->create()->id, 'nom' => 'Ndiaye', 'prenom' => 'Fatou']);
        $frais = FraisScolaire::create([
            'etudiant_id' => $eleve->id, 'etablissement_id' => $gestionnaire->etablissement_id,
            'libelle' => 'Scolarité', 'annee_scolaire' => '2025-2026', 'montant' => 50000,
        ]);
        $paiement = Paiement::create([
            'frais_scolaire_id' => $frais->id, 'etudiant_id' => $eleve->id,
            'etablissement_id' => $gestionnaire->etablissement_id, 'montant' => 20000,
            'date_paiement' => '2025-10-01', 'mode_paiement' => 'especes', 'statut' => 'completed',
        ]);

        return [$gestionnaire, $frais, $paiement];
    }

    /** Contenu XML de la première feuille d'un classeur .xlsx. */
    private function feuille(string $contenu): string
    {
        $chemin = tempnam(sys_get_temp_dir(), 'feuille');
        file_put_contents($chemin, $contenu);
        $zip = new \ZipArchive();
        $zip->open($chemin);
        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        @unlink($chemin);

        return (string) $xml;
    }

    public function test_export_excel_des_frais(): void
    {
        [$gestionnaire] = $this->fraisAvecPaiement();
        Sanctum::actingAs($gestionnaire);

        $res = $this->get('/api/frais-scolaires/export?annee_scolaire=2025-2026')->assertOk();
        $this->assertStringContainsString('spreadsheetml.sheet', $res->headers->get('Content-Type'));
        $this->assertStringContainsString('Ndiaye', $this->feuille($res->getContent()));
        $this->assertStringContainsString('Partiel', $this->feuille($res->getContent()));
    }

    public function test_recu_imprimable_du_paiement(): void
    {
        [$gestionnaire, , $paiement] = $this->fraisAvecPaiement();
        Sanctum::actingAs($gestionnaire);

        $this->get("/api/paiements/{$paiement->id}/recu")
            ->assertOk()
            ->assertSee('REC-'.str_pad((string) $paiement->id, 6, '0', STR_PAD_LEFT))
            ->assertSee('20 000 FCFA');
    }

    public function test_export_et_recu_refuses_aux_roles_sans_droit(): void
    {
        [, , $paiement] = $this->fraisAvecPaiement();
        Sanctum::actingAs(User::factory()->create(['role' => 'enseignant']));

        $this->get('/api/frais-scolaires/export')->assertForbidden();
        $this->get("/api/paiements/{$paiement->id}/recu")->assertForbidden();
    }
}
