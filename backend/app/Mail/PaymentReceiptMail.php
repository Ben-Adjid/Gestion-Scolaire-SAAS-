<?php

namespace App\Mail;

use App\Models\Paiement;
use App\Services\RecuPaiement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Reçu de paiement envoyé au payeur ou au tuteur, avec le reçu PDF en pièce jointe.
 * Envoyé en file d'attente : l'encaissement n'attend jamais le serveur SMTP.
 */
class PaymentReceiptMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** Nombre de tentatives avant abandon, et délai entre elles (secondes). */
    public int $tries = 3;

    public int $timeout = 60;

    /** @var array<int, int> */
    public array $backoff = [30, 120];

    public function __construct(public Paiement $paiement)
    {
    }

    public function envelope(): Envelope
    {
        $donnees = RecuPaiement::donnees($this->paiement);

        return new Envelope(subject: "Reçu de paiement {$donnees['numero']} — {$donnees['ecole']['nom']}");
    }

    public function content(): Content
    {
        $donnees = RecuPaiement::donnees($this->paiement);

        return new Content(view: 'emails.recu-paiement', with: ['d' => $donnees]);
    }

    public function attachments(): array
    {
        $numero = RecuPaiement::donnees($this->paiement)['numero'];

        return [
            Attachment::fromData(fn () => RecuPaiement::generer($this->paiement), "recu-{$numero}.pdf")
                ->withMime('application/pdf'),
        ];
    }

    /** Appelée par la file d'attente après la dernière tentative échouée : l'erreur reste dans les journaux. */
    public function failed(?Throwable $exception): void
    {
        Log::error('Envoi du reçu par e-mail abandonné après plusieurs tentatives.', [
            'paiement_id' => $this->paiement->id,
            'erreur' => $exception?->getMessage(),
        ]);
    }
}
