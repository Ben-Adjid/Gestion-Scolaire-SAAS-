<?php

namespace App\Console\Commands;

use App\Models\Etablissement;
use Illuminate\Console\Command;

/** Passe en « expire » les établissements dont la date de fin d'abonnement est dépassée. */
class ExpirerAbonnements extends Command
{
    protected $signature = 'abonnements:expirer';

    protected $description = 'Marque comme expirés les abonnements échus.';

    public function handle(): int
    {
        $total = Etablissement::query()
            ->whereIn('statut_abonnement', ['actif', 'essai'])
            ->whereNotNull('abonnement_fin')
            ->whereDate('abonnement_fin', '<', today())
            ->update(['statut_abonnement' => 'expire']);

        $this->info("{$total} abonnement(s) expiré(s).");

        return self::SUCCESS;
    }
}
