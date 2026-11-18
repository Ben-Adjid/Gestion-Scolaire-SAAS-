<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/** Invitation d'un administrateur d'établissement : il active son compte et choisit son mot de passe (valable 72 heures). */
class ActivationCompte extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $lien, public string $nomEcole, public string $nomAdmin) {}

    public function build(): self
    {
        return $this->subject('Activez votre espace '.$this->nomEcole)
            ->html(
                '<p>Bonjour '.e($this->nomAdmin).',</p>'
                .'<p>Votre établissement <strong>'.e($this->nomEcole).'</strong> a été créé sur la plateforme de gestion scolaire.</p>'
                .'<p>Pour activer votre compte administrateur, cliquez sur le lien ci-dessous et choisissez votre mot de passe :</p>'
                .'<p><a href="'.e($this->lien).'">Activer mon compte</a></p>'
                .'<p>Ce lien expire dans 72 heures. Si vous n’attendiez pas ce message, ignorez-le : aucun accès n’est ouvert sans votre mot de passe.</p>'
            );
    }
}
