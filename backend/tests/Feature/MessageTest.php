<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_envoie_une_annonce_aux_enseignants(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $enseignant = User::factory()->create(['role' => 'enseignant']);

        Sanctum::actingAs($admin);

        $this->postJson('/api/messages', [
            'type' => 'annonce',
            'cible' => 'enseignants',
            'titre' => 'Réunion du personnel',
            'contenu' => 'Réunion lundi à 8h.',
        ])->assertCreated();

        // L'enseignant voit le message
        Sanctum::actingAs($enseignant);

        $this->getJson('/api/messages')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.titre', 'Réunion du personnel');
    }

    public function test_enseignant_envoie_un_sujet_a_l_administration(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $enseignant = User::factory()->create(['role' => 'enseignant']);

        Sanctum::actingAs($enseignant);

        $this->postJson('/api/messages', [
            'type' => 'sujet',
            'cible' => 'administration',
            'titre' => 'Sujet de mathématiques',
            'contenu' => 'Sujet du premier trimestre.',
        ])->assertCreated();

        // L'admin voit le sujet
        Sanctum::actingAs($admin);

        $this->getJson('/api/messages')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_marquage_lu_reduit_les_non_lus(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $enseignant = User::factory()->create(['role' => 'enseignant']);

        Message::create([
            'sender_id' => $admin->id,
            'recipient_id' => $enseignant->id,
            'etablissement_id' => $enseignant->etablissement_id,
            'type' => 'annonce',
            'subject' => 'Annonce',
            'body' => 'Contenu',
        ]);

        Sanctum::actingAs($enseignant);

        $this->getJson('/api/messages/stats')->assertJsonPath('non_lus', 1);

        $message = Message::first();
        $this->postJson("/api/messages/{$message->id}/lu")->assertOk();

        $this->getJson('/api/messages/stats')->assertJsonPath('non_lus', 0);
    }
}
