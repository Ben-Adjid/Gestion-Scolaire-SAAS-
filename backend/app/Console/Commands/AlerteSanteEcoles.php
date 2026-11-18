<?php

namespace App\Console\Commands;

use App\Mail\AlerteSanteEcole;
use App\Models\Etablissement;
use App\Models\User;
use App\Services\ScoreSante;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/** Chaque jour : alerte la plateforme quand une école active passe sous 50/100. */
class AlerteSanteEcoles extends Command
{
    public const SEUIL = 50;

    protected $signature = 'sante:alertes';

    protected $description = 'Alerte la plateforme quand une école perd de l\'activité.';

    public function handle(): int
    {
        $plateforme = User::withoutGlobalScopes()->where('role', 'super_admin')->pluck('email');
        $alertes = 0;

        Etablissement::where('statut_abonnement', 'actif')->get()->each(function (Etablissement $etab) use ($plateforme, &$alertes) {
            $sante = ScoreSante::pour($etab);
            if ($sante['score'] > self::SEUIL) {
                return;
            }

            $manques = collect($sante['criteres'])->reject(fn ($c) => $c['ok'])->pluck('libelle')->all();
            foreach ($plateforme as $email) {
                Mail::to($email)->queue(new AlerteSanteEcole($etab->nom, $sante['score'], $manques));
            }
            $alertes++;
        });

        $this->info("Alertes de santé : {$alertes}.");

        return self::SUCCESS;
    }
}
