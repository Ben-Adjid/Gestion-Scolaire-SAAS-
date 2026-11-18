<?php

namespace App\Mail;

use App\Models\Etablissement;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/** E-mail transactionnel de relance avant l'expiration de l'abonnement. */
class RelanceAbonnement extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Etablissement $etablissement,
        public int $joursAvant,
        public string $corps,
    ) {}

    public function build(): self
    {
        return $this->subject("Votre abonnement expire dans {$this->joursAvant} jours")
            ->html(nl2br(e($this->corps)));
    }
}
