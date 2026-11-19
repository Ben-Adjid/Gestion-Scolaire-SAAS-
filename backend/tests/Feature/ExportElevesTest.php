<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\Etablissement;
use App\Models\Etudiant;
use App\Models\Inscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Export des élèves : colonnes fixes, statut d'inscription, contact du tuteur, filtres communs avec l'écran. */
class ExportElevesTest extends TestCase
{
    use RefreshDatabase;

    private Etablissement $etab;
    private Classe $classe;

    protected function setUp(): void
    {
        parent::setUp();
        $this->etab = Etablissement::create(['nom' => 'École Export', 'plan' => 'vip', 'statut_abonnement' => 'actif', 'annee_scolaire' => '2026-2027']);
        $this->classe = Classe::create(['nom' => '6e A', 'niveau' => '6ème', 'etablissement_id' => $this->etab->id]);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'etablissement_id' => $this->etab->id]));
    }

    private function eleve(string $nom, string $genre, ?string $statut = 'active'): Etudiant
    {
        $eleve = Etudiant::create([
            'nom' => $nom, 'prenom' => 'Test', 'genre' => $genre, 'date_naissance' => '2012-01-01',
            'classe_id' => $this->classe->id, 'etablissement_id' => $this->etab->id,
        ]);
        if ($statut) {
            Inscription::create([
                'etudiant_id' => $eleve->id, 'classe_id' => $this->classe->id, 'annee_scolaire' => '2026-2027',
                'type' => 'nouveau', 'date_inscription' => '2026-09-01', 'statut' => $statut, 'etablissement_id' => $this->etab->id,
            ]);
        }

        return $eleve;
    }

    /** @return array<int, array<int, string>> lignes CSV (sans en-tête) */
    private function csv(array $parametres = []): array
    {
        $contenu = $this->get('/api/export/etudiants?'.http_build_query(['format' => 'csv'] + $parametres))
            ->assertOk()->streamedContent();
        $contenu = ltrim($contenu, "\xEF\xBB\xBF");
        $lignes = array_values(array_filter(explode("\n", trim($contenu))));

        return array_map(fn ($l) => str_getcsv($l, ';'), $lignes);
    }

    public function test_export_a_les_colonnes_demandees_avec_statut_et_tuteur(): void
    {
        $eleve = $this->eleve('Diop', 'F');
        $tuteur = User::factory()->create(['role' => 'parent', 'etablissement_id' => $this->etab->id, 'telephone' => '+235 66 00 11 22', 'email' => 'parent.diop@exemple.test']);
        $eleve->parents()->attach($tuteur->id, ['lien' => 'pere']);
        $this->eleve('Ali', 'M', null);

        $lignes = $this->csv();

        $this->assertSame(['Matricule', 'Nom', 'Prénom', 'Sexe', 'Classe', 'Statut', 'Contact Tuteur', 'Email Tuteur'], $lignes[0]);
        $diop = collect($lignes)->firstWhere(1, 'Diop');
        $this->assertSame(['Féminin', '6e A', 'Actif', '+235 66 00 11 22', 'parent.diop@exemple.test'], array_slice($diop, 3));
        $ali = collect($lignes)->firstWhere(1, 'Ali');
        $this->assertSame(['Masculin', '6e A', 'Inactif', '', ''], array_slice($ali, 3));
    }

    public function test_export_applique_les_memes_filtres_que_l_ecran(): void
    {
        $this->eleve('Diop', 'F');
        $this->eleve('Ali', 'M');
        $this->eleve('Sow', 'F', null);

        $feminin = array_slice($this->csv(['genre' => 'F']), 1);
        $this->assertSame(['Diop', 'Sow'], array_column($feminin, 1));

        $actifs = array_slice($this->csv(['statut' => 'actif']), 1);
        $this->assertSame(['Ali', 'Diop'], array_column($actifs, 1));

        $recherche = array_slice($this->csv(['search' => 'so']), 1);
        $this->assertSame(['Sow'], array_column($recherche, 1));
    }

    public function test_le_filtre_genre_de_la_liste_renvoie_seulement_le_genre_choisi(): void
    {
        $this->eleve('Diop', 'F');
        $this->eleve('Ali', 'M');

        $reponse = $this->getJson('/api/etudiants?genre=M')->assertOk();
        $this->assertSame(['Ali'], collect($reponse->json('data'))->pluck('nom')->all());
    }
}
