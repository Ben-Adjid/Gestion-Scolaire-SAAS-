<?php

namespace Tests\Feature;

use App\Mail\ResumeHebdomadaire;
use App\Models\Absence;
use App\Models\Classe;
use App\Models\Etablissement;
use App\Models\Etudiant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Résumé hebdomadaire : envoyé à la direction de chaque école active, avec les chiffres de la semaine. */
class ResumeHebdomadaireTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_direction_recoit_le_resume_avec_les_absences_de_la_semaine(): void
    {
        Mail::fake();
        $etab = Etablissement::create(['nom' => 'Semaine', 'plan' => 'standard', 'statut_abonnement' => 'actif']);
        $admin = User::factory()->create(['role' => 'admin', 'etablissement_id' => $etab->id, 'email' => 'direction@test.com']);
        $proviseur = User::factory()->create(['role' => 'proviseur', 'etablissement_id' => $etab->id, 'email' => 'proviseur.semaine@test.com']);
        $classe = Classe::factory()->create(['etablissement_id' => $etab->id]);
        $eleve = Etudiant::factory()->create(['etablissement_id' => $etab->id, 'classe_id' => $classe->id]);
        Absence::create(['etudiant_id' => $eleve->id, 'date' => now()->subDays(2)->toDateString(), 'type' => 'absence', 'justifie' => false]);

        $this->artisan('resume:hebdomadaire')->assertSuccessful();

        Mail::assertQueued(ResumeHebdomadaire::class, fn ($mail) => $mail->hasTo('direction@test.com') && str_contains($mail->render(), '<strong>1</strong>'));
        Mail::assertQueued(ResumeHebdomadaire::class, fn ($mail) => $mail->hasTo('proviseur.semaine@test.com'));
        Mail::assertQueued(ResumeHebdomadaire::class, 2);
        $this->assertNotNull($admin->id);
        $this->assertNotNull($proviseur->id);
    }
}
