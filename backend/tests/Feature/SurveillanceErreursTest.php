<?php

namespace Tests\Feature;

use App\Mail\AlerteErreurServeur;
use App\Models\Etablissement;
use App\Models\User;
use App\Services\SurveillanceErreurs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

/** Surveillance des erreurs serveur : enregistrement, regroupement, alerte limitée, consultation réservée. */
class SurveillanceErreursTest extends TestCase
{
    use RefreshDatabase;

    private function erreur(): RuntimeException
    {
        return new RuntimeException('Connexion à la base perdue');
    }

    public function test_une_erreur_est_enregistree_puis_comptee(): void
    {
        $service = app(SurveillanceErreurs::class);
        $erreur = $this->erreur();

        $service->enregistrer($erreur);
        $service->enregistrer($erreur);

        $ligne = DB::table('erreurs_serveur')->first();
        $this->assertSame(RuntimeException::class, $ligne->classe);
        $this->assertSame(2, (int) $ligne->occurrences);
    }

    public function test_un_refus_normal_n_est_pas_une_erreur(): void
    {
        app(SurveillanceErreurs::class)->enregistrer(new NotFoundHttpException('Page introuvable'));

        $this->assertSame(0, DB::table('erreurs_serveur')->count());
    }

    public function test_en_production_une_seule_alerte_par_heure(): void
    {
        Mail::fake();
        User::factory()->create(['role' => 'super_admin', 'etablissement_id' => null, 'email' => 'plateforme@test.com']);
        $this->app['env'] = 'production';
        $erreur = $this->erreur();

        app(SurveillanceErreurs::class)->enregistrer($erreur);
        app(SurveillanceErreurs::class)->enregistrer($erreur);

        Mail::assertQueued(AlerteErreurServeur::class, 1);
        $this->app['env'] = 'testing';
    }

    public function test_seul_le_super_admin_consulte_et_resout_les_erreurs(): void
    {
        app(SurveillanceErreurs::class)->enregistrer($this->erreur());
        $id = DB::table('erreurs_serveur')->value('id');

        $etab = Etablissement::create(['nom' => 'École', 'plan' => 'entreprise', 'statut_abonnement' => 'actif']);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'etablissement_id' => $etab->id]));
        $this->getJson('/api/super-admin/erreurs')->assertForbidden();

        Sanctum::actingAs(User::factory()->create(['role' => 'super_admin', 'etablissement_id' => null]));
        $this->getJson('/api/super-admin/erreurs')->assertOk()->assertJsonPath('data.0.message', 'Connexion à la base perdue');
        $this->deleteJson("/api/super-admin/erreurs/{$id}")->assertOk();
        $this->assertSame(0, DB::table('erreurs_serveur')->count());
    }
}
