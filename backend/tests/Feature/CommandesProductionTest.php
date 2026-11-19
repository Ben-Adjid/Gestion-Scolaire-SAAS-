<?php

namespace Tests\Feature;

use App\Mail\PaymentReceiptMail;
use App\Models\Etablissement;
use App\Models\Etudiant;
use App\Models\FraisScolaire;
use App\Models\User;
use Database\Seeders\ProductionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

/** Création du Super Admin en console et séparation des données de production. */
class CommandesProductionTest extends TestCase
{
    use RefreshDatabase;

    public function test_make_superadmin_cree_un_compte_plateforme(): void
    {
        $this->artisan('make:superadmin')
            ->expectsQuestion('Nom complet', 'Responsable Plateforme')
            ->expectsQuestion('Adresse e-mail', 'root@plateforme.test')
            ->expectsQuestion('Mot de passe (12 caractères minimum)', 'MotDePasseSolide-2026')
            ->expectsQuestion('Confirmer le mot de passe', 'MotDePasseSolide-2026')
            ->expectsQuestion('Téléphone (ex. +221770000000)', '+221770000000')
            ->assertSuccessful();

        $compte = User::withoutGlobalScopes()->where('email', 'root@plateforme.test')->firstOrFail();
        $this->assertSame('super_admin', $compte->role);
        $this->assertNull($compte->etablissement_id);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('MotDePasseSolide-2026', $compte->password));
    }

    public function test_make_superadmin_refuse_un_mot_de_passe_trop_court(): void
    {
        $this->artisan('make:superadmin')
            ->expectsQuestion('Nom complet', 'Responsable')
            ->expectsQuestion('Adresse e-mail', 'court@plateforme.test')
            ->expectsQuestion('Mot de passe (12 caractères minimum)', 'court')
            ->assertFailed();

        $this->assertSame(0, User::withoutGlobalScopes()->where('email', 'court@plateforme.test')->count());
    }

    public function test_production_seeder_insere_le_catalogue_sans_compte_de_test(): void
    {
        $this->seed(ProductionSeeder::class);
        $this->seed(ProductionSeeder::class); // relançable sans doublon

        $nombre = collect(config('permissions.modules'))->sum(fn ($codes) => count($codes));
        $this->assertSame($nombre, DB::table('permissions')->count());
        $this->assertSame(0, User::withoutGlobalScopes()->where('email', 'like', '%@test.com')->count());
    }

    public function test_un_envoi_abandonne_est_journalise_sans_erreur_visible(): void
    {
        Log::spy();
        $etab = Etablissement::create(['nom' => 'École Log', 'plan' => 'vip', 'statut_abonnement' => 'actif']);
        $eleve = Etudiant::create(['nom' => 'Test', 'prenom' => 'Log', 'genre' => 'M', 'date_naissance' => '2010-01-01', 'etablissement_id' => $etab->id]);
        $frais = FraisScolaire::create(['etudiant_id' => $eleve->id, 'libelle' => 'Scolarité', 'montant' => 1000, 'annee_scolaire' => '2026-2027', 'statut' => 'en_attente', 'etablissement_id' => $etab->id]);
        $paiement = $frais->paiements()->create(['etudiant_id' => $eleve->id, 'montant' => 500, 'date_paiement' => now()->toDateString(), 'mode_paiement' => 'especes', 'statut' => 'completed', 'etablissement_id' => $etab->id]);

        (new PaymentReceiptMail($paiement))->failed(new \RuntimeException('SMTP injoignable'));

        Log::shouldHaveReceived('error')->once();
    }
}
