<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/** Prévient la plateforme qu'une école perd de l'activité (score de santé faible). */
class AlerteSanteEcole extends Mailable
{
    use Queueable, SerializesModels;

    /** @param list<string> $manques */
    public function __construct(public string $nomEtablissement, public int $score, public array $manques) {}

    public function build(): self
    {
        $liste = '';
        foreach ($this->manques as $manque) {
            $liste .= '<li>'.e($manque).'</li>';
        }

        return $this->subject("Alerte : {$this->nomEtablissement} perd de l'activité (score {$this->score}/100)")
            ->html('<p><strong>'.e($this->nomEtablissement).'</strong> a un score de santé de '.$this->score.'/100.</p>'
                .'<p>Points à vérifier :</p><ul>'.$liste.'</ul>'
                .'<p>Pensez à contacter l’école avant qu’elle ne parte.</p>');
    }
}
