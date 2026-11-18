<?php

namespace App\Services;

use App\Mail\AlerteErreurServeur;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Surveillance des erreurs serveur : chaque erreur inattendue est enregistrée et regroupée par signature.
 * En production, la plateforme reçoit un e-mail au plus une fois par heure et par erreur.
 * Les refus normaux (validation, droits, page introuvable…) ne sont pas des erreurs et sont ignorés.
 */
class SurveillanceErreurs
{
    private const IGNOREES = [
        ValidationException::class,
        AuthenticationException::class,
        AuthorizationException::class,
        ModelNotFoundException::class,
        TokenMismatchException::class,
    ];

    public function enregistrer(Throwable $erreur): void
    {
        try {
            if ($this->estIgnoree($erreur)) {
                return;
            }

            $fichier = str_replace(base_path().DIRECTORY_SEPARATOR, '', $erreur->getFile());
            $signature = sha1(get_class($erreur).'|'.$fichier.'|'.$erreur->getLine());
            $requete = app()->runningInConsole() ? null : request();
            $utilisateur = $requete?->user();
            $maintenant = now();

            $existante = DB::table('erreurs_serveur')->where('signature', $signature)->first();
            if ($existante) {
                DB::table('erreurs_serveur')->where('id', $existante->id)->update([
                    'occurrences' => $existante->occurrences + 1,
                    'message' => mb_substr($erreur->getMessage(), 0, 2000),
                    'derniere_fois' => $maintenant,
                    'updated_at' => $maintenant,
                ]);
            } else {
                DB::table('erreurs_serveur')->insert([
                    'signature' => $signature,
                    'classe' => get_class($erreur),
                    'message' => mb_substr($erreur->getMessage(), 0, 2000),
                    'fichier' => mb_substr($fichier, 0, 255),
                    'ligne' => $erreur->getLine(),
                    'methode' => $requete?->method(),
                    // Le chemin seulement : jamais les paramètres, qui peuvent contenir des données personnelles.
                    'url' => $requete ? mb_substr($requete->path(), 0, 500) : null,
                    'user_id' => $utilisateur?->id,
                    'etablissement_id' => $utilisateur?->etablissement_id,
                    'occurrences' => 1,
                    'premiere_fois' => $maintenant,
                    'derniere_fois' => $maintenant,
                    'created_at' => $maintenant,
                    'updated_at' => $maintenant,
                ]);
            }

            $this->alerter($signature, $erreur, $fichier);
        } catch (Throwable) {
            // La surveillance ne doit jamais provoquer elle-même une erreur.
        }
    }

    private function estIgnoree(Throwable $erreur): bool
    {
        foreach (self::IGNOREES as $classe) {
            if ($erreur instanceof $classe) {
                return true;
            }
        }

        // Les réponses HTTP 4xx (introuvable, interdit…) sont des refus normaux, pas des pannes.
        return $erreur instanceof HttpExceptionInterface && $erreur->getStatusCode() < 500;
    }

    private function alerter(string $signature, Throwable $erreur, string $fichier): void
    {
        if (! app()->environment('production')) {
            return;
        }
        // Une alerte par erreur et par heure au plus.
        if (! Cache::add('alerte-erreur:'.$signature, true, now()->addHour())) {
            return;
        }

        foreach (User::withoutGlobalScopes()->where('role', 'super_admin')->pluck('email') as $email) {
            Mail::to($email)->queue(new AlerteErreurServeur(get_class($erreur), $erreur->getMessage(), $fichier, $erreur->getLine()));
        }
    }
}
