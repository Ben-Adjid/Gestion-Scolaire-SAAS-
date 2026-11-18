<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * Sauvegarde quotidienne de la base de données (mysqldump) dans storage/app/sauvegardes.
 * Le mot de passe est transmis par la variable d'environnement MYSQL_PWD, jamais sur la ligne de commande.
 * Les sauvegardes de plus de 14 jours sont supprimées automatiquement.
 * Chemin du programme : variable MYSQLDUMP_PATH (par défaut « mysqldump », présent dans le PATH).
 */
class SauvegarderBase extends Command
{
    protected $signature = 'sauvegarde:executer';

    protected $description = 'Sauvegarde la base de données et supprime les sauvegardes de plus de 14 jours.';

    public function handle(): int
    {
        $connexion = config('database.connections.'.config('database.default'));
        $dossier = storage_path('app/sauvegardes');
        File::ensureDirectoryExists($dossier);

        $fichier = $dossier.DIRECTORY_SEPARATOR.$connexion['database'].'-'.now()->format('Y-m-d_H-i-s').'.sql';

        $resultat = Process::env(['MYSQL_PWD' => (string) $connexion['password']])
            ->timeout(900)
            ->run([
                env('MYSQLDUMP_PATH', 'mysqldump'),
                '--host='.$connexion['host'],
                '--port='.$connexion['port'],
                '--user='.$connexion['username'],
                '--single-transaction',
                '--routines',
                $connexion['database'],
            ]);

        if (! $resultat->successful()) {
            $this->error('Échec de la sauvegarde : '.trim($resultat->errorOutput()));

            return self::FAILURE;
        }

        File::put($fichier, $resultat->output());

        $limite = now()->subDays(14)->getTimestamp();
        foreach (File::files($dossier) as $ancien) {
            if ($ancien->getMTime() < $limite) {
                File::delete($ancien->getPathname());
            }
        }

        $this->info("Sauvegarde créée : {$fichier}");

        return self::SUCCESS;
    }
}
