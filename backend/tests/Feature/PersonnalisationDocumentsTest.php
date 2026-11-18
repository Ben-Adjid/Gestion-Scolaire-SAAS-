<?php

namespace Tests\Feature;

use App\Models\Etablissement;
use App\Models\Etudiant;
use App\Models\FraisScolaire;
use App\Models\Paiement;
use App\Models\User;
use App\Services\Xlsx;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use ZipArchive;

/** Documents produits aux couleurs de l'établissement : exports Excel titrés, reçu avec identité de l'école. */
class PersonnalisationDocumentsTest extends TestCase
{
    use RefreshDatabase;

    private function feuille(string $contenu): string
    {
        $chemin = tempnam(sys_get_temp_dir(), 'perso');
        file_put_contents($chemin, $contenu);
        $zip = new ZipArchive();
        $zip->open($chemin);
        $xml = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        @unlink($chemin);

        return $xml;
    }

    public function test_un_export_excel_porte_le_nom_de_l_ecole_et_se_reimporte(): void
    {
        $contenu = Xlsx::generer('Élèves', ['Nom', 'Prénom'], [['Sy', 'Awa']], [], 'Lycée Abéché — Élèves');
        $xml = $this->feuille($contenu);

        $this->assertStringContainsString('Lycée Abéché — Élèves', $xml);
        $this->assertStringContainsString('<mergeCell ref="A1:B1"/>', $xml);
        $this->assertStringContainsString('<autoFilter ref="A2:B3"/>', $xml);

        // Le fichier titré se relit : la ligne de titre est ignorée, les en-têtes sont retrouvés.
        $chemin = tempnam(sys_get_temp_dir(), 'relu');
        file_put_contents($chemin, $contenu);
        [$entetes, $lignes] = Xlsx::lire($chemin);
        @unlink($chemin);
        $this->assertSame(['Nom', 'Prénom'], $entetes);
        $this->assertSame([['Sy', 'Awa']], $lignes);
    }

    public function test_l_export_des_eleves_affiche_le_nom_de_l_etablissement(): void
    {
        $etab = Etablissement::create(['nom' => 'Collège Moundou', 'plan' => 'vip', 'statut_abonnement' => 'actif']);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'etablissement_id' => $etab->id]));

        $xml = $this->feuille($this->get('/api/export/etudiants')->assertOk()->getContent());

        $this->assertStringContainsString('Collège Moundou — Élèves', $xml);
    }

    public function test_le_recu_reprend_l_identite_de_l_ecole(): void
    {
        $etab = Etablissement::create([
            'nom' => 'Lycée Sarh', 'plan' => 'standard', 'statut_abonnement' => 'actif',
            'devise' => 'Travail et discipline', 'adresse' => 'Quartier Résidentiel, Sarh', 'telephone' => '+235 66 11 22 33',
        ]);
        $gestionnaire = User::factory()->create(['role' => 'gestionnaire', 'etablissement_id' => $etab->id]);
        Sanctum::actingAs($gestionnaire);
        $eleve = Etudiant::factory()->create(['etablissement_id' => $etab->id]);
        $frais = FraisScolaire::create(['etudiant_id' => $eleve->id, 'libelle' => 'Scolarité', 'montant' => 50000, 'annee_scolaire' => '2026-2027', 'statut' => 'pending', 'etablissement_id' => $etab->id]);
        $paiement = Paiement::create(['frais_scolaire_id' => $frais->id, 'etudiant_id' => $eleve->id, 'montant' => 20000, 'date_paiement' => '2026-11-18', 'mode_paiement' => 'especes', 'statut' => 'completed', 'etablissement_id' => $etab->id]);

        $this->get("/api/paiements/{$paiement->id}/recu")
            ->assertOk()
            ->assertSee('Lycée Sarh', false)
            ->assertSee('Travail et discipline', false)
            ->assertSee('Quartier Résidentiel, Sarh', false)
            ->assertSee('+235 66 11 22 33', false);
    }
}
