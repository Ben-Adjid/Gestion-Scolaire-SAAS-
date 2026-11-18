<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/** Alerte envoyée à la plateforme lorsqu'une erreur serveur survient en production. */
class AlerteErreurServeur extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $classe,
        public string $message,
        public string $fichier,
        public int $ligne,
    ) {}

    public function build(): self
    {
        return $this->subject('Erreur serveur : '.mb_substr($this->message, 0, 80))
            ->html(
                '<p>Une erreur est survenue sur la plateforme.</p>'
                .'<p><strong>'.e($this->classe).'</strong><br>'.e(mb_substr($this->message, 0, 500)).'</p>'
                .'<p>'.e($this->fichier).' : ligne '.$this->ligne.'</p>'
                .'<p>Le détail et le nombre d’occurrences sont visibles dans « Erreurs serveur » (super admin).</p>'
            );
    }
}
