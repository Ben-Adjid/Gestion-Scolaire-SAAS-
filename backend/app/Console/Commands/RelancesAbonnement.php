<?php

namespace App\Console\Commands;

use App\Mail\RelanceAbonnement;
use App\Models\Etablissement;
use App\Models\User;
use App\Services\WhatsAppService;
use Closure;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Relances automatiques à J-7 et J-3 avant la fin d'abonnement : e-mail et WhatsApp
 * aux administrateurs. Chaque relance est journalisée ; une relance déjà envoyée n'est jamais renvoyée.
 * Le bandeau du tableau de bord est calculé à chaque affichage (jours_restants).
 */
class RelancesAbonnement extends Command
{
    protected $signature = 'abonnements:relances';

    protected $description = 'Envoie les relances d\'abonnement à 7 et 3 jours de l\'échéance.';

    public function handle(WhatsAppService $whatsapp): int
    {
        $envoyees = 0;

        foreach ([7, 3] as $jours) {
            $echeance = today()->addDays($jours);

            Etablissement::query()
                ->where('statut_abonnement', 'actif')
                ->whereDate('abonnement_fin', $echeance->toDateString())
                ->each(function (Etablissement $etablissement) use ($jours, $echeance, $whatsapp, &$envoyees) {
                    $texte = "Bonjour, l'abonnement de {$etablissement->nom} expire le {$echeance->format('d/m/Y')} "
                        ."(dans {$jours} jours). Renouvelez-le pour éviter toute suspension de l'espace.";

                    $emails = User::withoutGlobalScopes()
                        ->where('etablissement_id', $etablissement->id)
                        ->where('role', 'admin')
                        ->pluck('email');

                    $envoyees += $this->envoyerUneFois($etablissement, $echeance, $jours, 'email', function () use ($emails, $etablissement, $jours, $texte) {
                        foreach ($emails as $email) {
                            Mail::to($email)->queue(new RelanceAbonnement($etablissement, $jours, $texte));
                        }

                        return $emails->isNotEmpty();
                    });

                    if ($etablissement->telephone) {
                        $envoyees += $this->envoyerUneFois($etablissement, $echeance, $jours, 'whatsapp', fn () => $whatsapp->envoyer($etablissement->telephone, $texte));
                    }
                });
        }

        $this->info("{$envoyees} relance(s) envoyée(s).");

        return self::SUCCESS;
    }

    /**
     * Réserve la relance dans le journal, l'envoie, puis la libère si l'envoi a échoué
     * (elle sera retentée à la prochaine exécution). Retourne 1 si envoyée, 0 sinon.
     */
    private function envoyerUneFois(Etablissement $etablissement, $echeance, int $jours, string $canal, Closure $envoi): int
    {
        $cle = [
            'etablissement_id' => $etablissement->id,
            'date_fin' => $echeance->toDateString(),
            'jours_avant' => $jours,
            'canal' => $canal,
        ];

        $reservee = DB::table('relances_abonnement')->insertOrIgnore($cle + ['envoye' => false, 'created_at' => now(), 'updated_at' => now()]);
        if ($reservee === 0) {
            return 0;
        }

        if ($envoi()) {
            DB::table('relances_abonnement')->where($cle)->update(['envoye' => true, 'updated_at' => now()]);

            return 1;
        }

        DB::table('relances_abonnement')->where($cle)->delete();

        return 0;
    }
}
