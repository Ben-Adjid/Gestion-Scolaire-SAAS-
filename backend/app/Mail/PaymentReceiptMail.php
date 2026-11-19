<?php

namespace App\Mail;

use App\Models\Paiement;
use App\Services\IdentiteEtablissement;
use App\Services\RecuPaiement;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Reçu de paiement envoyé au payeur ou au tuteur, avec le reçu PDF en pièce jointe. */
class PaymentReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

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
            \Illuminate\Mail\Mailables\Attachment::fromData(fn () => RecuPaiement::generer($this->paiement), "recu-{$numero}.pdf")
                ->withMime('application/pdf'),
        ];
    }
}
