<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/** Bienvenue : compte créé. Le mot de passe n'est jamais écrit dans l'e-mail. */
class CompteCree extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $nomCompte,
        public string $roleLibelle,
        public string $nomEtablissement,
    ) {}

    public function build(): self
    {
        $lien = rtrim(config('app.frontend_url'), '/').'/login';

        return $this->subject("Votre accès à {$this->nomEtablissement}")
            ->html(
                '<p>Bonjour '.e($this->nomCompte).',</p>'
                .'<p>Votre compte <strong>'.e($this->roleLibelle).'</strong> a été créé pour <strong>'.e($this->nomEtablissement).'</strong>.</p>'
                .'<p>Connectez-vous avec votre adresse e-mail : <a href="'.e($lien).'">'.e($lien).'</a></p>'
                .'<p>Le mot de passe vous a été communiqué par l’administration. Pour des raisons de sécurité, il ne figure pas dans ce message : '
                .'changez-le dès votre première connexion (Sécurité du compte).</p>'
            );
    }
}
