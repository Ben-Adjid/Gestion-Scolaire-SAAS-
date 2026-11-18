<?php

namespace Tests\Feature;

use App\Mail\NotificationParent;
use App\Models\Absence;
use App\Models\Classe;
use App\Models\Etablissement;
use App\Models\Etudiant;
use App\Models\Matiere;
use App\Models\NotificationEnvoyee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Notifications aux parents, présence du jour, planning des examens, fil d'actualité. */
class NotificationsEtFonctionnalitesVipTest extends TestCase
{
    use RefreshDatabase;

    private Etablissement $vip;
    private Etudiant $eleve;
    private User $admin;
    private User $parent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vip = Etablissement::create(['nom' => 'Lycée VIP', 'plan' => 'vip', 'statut_abonnement' => 'actif']);
        $classe = Classe::factory()->create(['etablissement_id' => $this->vip->id]);
        $this->eleve = Etudiant::factory()->create(['etablissement_id' => $this->vip->id, 'classe_id' => $classe->id, 'prenom' => 'Awa', 'nom' => 'Diop']);
        $this->admin = User::factory()->create(['role' => 'admin', 'etablissement_id' => $this->vip->id]);
        $this->parent = User::factory()->create([
            'role' => 'parent', 'etablissement_id' => $this->vip->id, 'email' => 'parent-notif@test.com',
        ]);
        $this->parent->enfants()->attach($this->eleve->id, ['lien' => 'pere']);
    }

    public function test_absence_previent_le_parent_par_e_mail(): void
    {
        Mail::fake();
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/absences', ['etudiant_id' => $this->eleve->id, 'date' => '2026-11-05', 'type' => 'absence'])
            ->assertCreated();

        Mail::assertQueued(NotificationParent::class, fn ($mail) => $mail->hasTo('parent-notif@test.com'));
        $this->assertDatabaseHas('notifications_envoyees', ['destinataire_id' => $this->parent->id, 'canal' => 'email', 'statut' => 'envoyee']);
    }

    public function test_une_meme_absence_n_est_notifiee_qu_une_fois(): void
    {
        Mail::fake();
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/absences', ['etudiant_id' => $this->eleve->id, 'date' => '2026-11-05', 'type' => 'absence'])->assertCreated();
        $this->postJson('/api/absences', ['etudiant_id' => $this->eleve->id, 'date' => '2026-11-05', 'type' => 'absence'])->assertCreated();

        Mail::assertQueued(NotificationParent::class, 1);
        $this->assertSame(1, NotificationEnvoyee::count());
    }

    public function test_standard_peut_envoyer_une_notification_parent_si_le_mail_est_configure(): void
    {
        Mail::fake();
        $standard = Etablissement::create(['nom' => 'Lycée Standard', 'plan' => 'standard', 'statut_abonnement' => 'actif']);
        $eleve = Etudiant::factory()->create(['etablissement_id' => $standard->id]);
        $admin = User::factory()->create(['role' => 'admin', 'etablissement_id' => $standard->id]);
        $parent = User::factory()->create(['role' => 'parent', 'etablissement_id' => $standard->id, 'email' => 'std@test.com']);
        $parent->enfants()->attach($eleve->id, ['lien' => 'mere']);
        Sanctum::actingAs($admin);

        $this->postJson('/api/absences', ['etudiant_id' => $eleve->id, 'date' => '2026-11-05', 'type' => 'absence'])->assertCreated();

        Mail::assertQueued(NotificationParent::class, fn ($mail) => $mail->hasTo('std@test.com'));
        $this->assertDatabaseHas('notifications_envoyees', ['destinataire_id' => $parent->id, 'canal' => 'email', 'statut' => 'envoyee']);
    }

    public function test_convocation_previent_le_parent(): void
    {
        Mail::fake();
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/convocations', ['etudiant_id' => $this->eleve->id, 'date' => '2026-11-10', 'motif' => 'Comportement'])
            ->assertCreated();

        Mail::assertQueued(NotificationParent::class, 1);
    }

    public function test_presence_du_jour_reservee_au_vip_et_compte_les_absents(): void
    {
        Absence::create(['etudiant_id' => $this->eleve->id, 'date' => today()->toDateString(), 'type' => 'absence', 'justifie' => false]);
        Sanctum::actingAs($this->admin);

        $this->getJson('/api/presence-du-jour')
            ->assertOk()
            ->assertJsonPath('absents', 1)
            ->assertJsonPath('retards', 0);

        $standard = Etablissement::create(['nom' => 'Standard', 'plan' => 'standard', 'statut_abonnement' => 'actif']);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'etablissement_id' => $standard->id]));
        $this->getJson('/api/presence-du-jour')->assertForbidden();
    }

    public function test_planning_des_examens_visible_par_le_parent_et_conflit_refuse(): void
    {
        $matiere = Matiere::factory()->create(['etablissement_id' => $this->vip->id]);
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/planning-examens', [
            'classe_id' => $this->eleve->classe_id, 'matiere_id' => $matiere->id,
            'date' => now()->addDays(10)->toDateString(), 'heure_debut' => '08:00', 'duree_minutes' => 120, 'salle' => 'B2',
        ])->assertCreated();

        $this->postJson('/api/planning-examens', [
            'classe_id' => $this->eleve->classe_id, 'matiere_id' => $matiere->id,
            'date' => now()->addDays(10)->toDateString(), 'heure_debut' => '08:00', 'duree_minutes' => 60,
        ])->assertStatus(422);

        Sanctum::actingAs($this->parent);
        $this->getJson("/api/parent/enfants/{$this->eleve->id}/resume")
            ->assertOk()
            ->assertJsonCount(1, 'examens')
            ->assertJsonPath('examens.0.salle', 'B2');
    }

    public function test_fil_d_actualite_publie_par_la_direction_lu_par_le_parent(): void
    {
        Sanctum::actingAs($this->admin);
        $this->postJson('/api/actualites', ['titre' => 'Sortie scolaire', 'contenu' => 'Visite du musée vendredi.'])->assertCreated();

        Sanctum::actingAs($this->parent);
        $this->getJson('/api/actualites')->assertOk()->assertJsonPath('0.titre', 'Sortie scolaire');
        $this->postJson('/api/actualites', ['titre' => 'Intrus', 'contenu' => 'Non'])->assertForbidden();
    }

    public function test_numero_de_telephone_du_compte_est_enregistre(): void
    {
        Sanctum::actingAs($this->admin);
        $this->postJson('/api/users', [
            'name' => 'Parent Tél', 'email' => 'tel@test.com', 'role' => 'parent', 'password' => 'secret123', 'telephone' => '+235 66 11 22 33',
        ])->assertCreated();

        $this->assertDatabaseHas('users', ['email' => 'tel@test.com', 'telephone' => '+235 66 11 22 33']);
        $this->assertTrue(DB::table('users')->where('email', 'tel@test.com')->exists());
    }
}
