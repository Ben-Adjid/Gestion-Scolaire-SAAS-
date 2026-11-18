<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/** Lien de réinitialisation du mot de passe (valable 60 minutes). */
class ReinitialisationMotDePasse extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $lien) {}

    public function build(): self
    {
        return $this->subject('Réinitialisation de votre mot de passe')
            ->html(
                '<p>Vous avez demandé à réinitialiser votre mot de passe.</p>'
                .'<p><a href="'.e($this->lien).'">Choisir un nouveau mot de passe</a></p>'
                .'<p>Ce lien expire dans 60 minutes. Si vous n’êtes pas à l’origine de cette demande, ignorez ce message : votre mot de passe reste inchangé.</p>'
            );
    }
}
