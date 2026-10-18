<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MessageFichierTest extends TestCase
{
    use RefreshDatabase;

    public function test_piece_jointe_enregistree_et_telechargeable_par_le_destinataire(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $enseignant = User::factory()->create(['role' => 'enseignant', 'etablissement_id' => $admin->etablissement_id]);

        Sanctum::actingAs($enseignant);
        $this->post('/api/messages', [
            'type' => 'sujet',
            'cible' => 'administration',
            'titre' => 'Sujet de maths',
            'contenu' => 'Voir le fichier.',
            'fichier' => UploadedFile::fake()->create('sujet.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $message = Message::where('recipient_id', $admin->id)->firstOrFail();
        $this->assertEquals('sujet.pdf', $message->fichier_nom);
        Storage::disk('local')->assertExists($message->fichier_path);

        Sanctum::actingAs($admin);
        $this->get("/api/messages/{$message->id}/fichier")
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=sujet.pdf');
    }

    public function test_un_tiers_ne_peut_pas_telecharger_la_piece_jointe(): void
    {
        Storage::fake('local');
        $expediteur = User::factory()->create(['role' => 'admin']);
        $destinataire = User::factory()->create(['role' => 'enseignant', 'etablissement_id' => $expediteur->etablissement_id]);
        $tiers = User::factory()->create(['role' => 'enseignant', 'etablissement_id' => $expediteur->etablissement_id]);

        $message = Message::create([
            'sender_id' => $expediteur->id,
            'recipient_id' => $destinataire->id,
            'subject' => 'Sujet',
            'body' => 'Corps',
            'type' => 'annonce',
            'fichier_path' => 'messages/fake.pdf',
            'fichier_nom' => 'fake.pdf',
            'etablissement_id' => $expediteur->etablissement_id,
        ]);

        Sanctum::actingAs($tiers);
        $this->getJson("/api/messages/{$message->id}/fichier")->assertForbidden();
    }

    public function test_type_de_fichier_non_autorise_est_refuse(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $enseignant = User::factory()->create(['role' => 'enseignant', 'etablissement_id' => $admin->etablissement_id]);

        Sanctum::actingAs($enseignant);
        $this->post('/api/messages', [
            'type' => 'sujet',
            'titre' => 'Script',
            'contenu' => 'Non.',
            'fichier' => UploadedFile::fake()->create('script.php', 10, 'application/x-php'),
        ], ['Accept' => 'application/json'])->assertStatus(422);
    }
}
