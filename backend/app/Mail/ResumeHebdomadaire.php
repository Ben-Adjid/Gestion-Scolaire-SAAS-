<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/** Résumé de la semaine écoulée, envoyé à la direction de l'école. */
class ResumeHebdomadaire extends Mailable
{
    use Queueable, SerializesModels;

    /** @param array<string, int|float> $indicateurs */
    public function __construct(
        public string $nomEtablissement,
        public string $periode,
        public array $indicateurs,
    ) {}

    public function build(): self
    {
        $lignes = '';
        foreach ($this->indicateurs as $libelle => $valeur) {
            $lignes .= '<tr><td style="padding:6px 12px 6px 0">'.e($libelle).'</td><td style="padding:6px 0"><strong>'.e((string) $valeur).'</strong></td></tr>';
        }

        return $this->subject("Résumé de la semaine — {$this->nomEtablissement}")
            ->html(
                '<p>Résumé de la semaine du '.e($this->periode).' pour <strong>'.e($this->nomEtablissement).'</strong> :</p>'
                .'<table>'.$lignes.'</table>'
                .'<p>Ce message est envoyé automatiquement chaque lundi matin.</p>'
            );
    }
}
