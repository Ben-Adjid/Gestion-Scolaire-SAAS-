<?php

namespace App\Console\Commands;

use App\Mail\ResumeHebdomadaire as ResumeMail;
use App\Models\Absence;
use App\Models\Etablissement;
use App\Models\Etudiant;
use App\Models\Note;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/** Chaque lundi : résumé de la semaine écoulée envoyé à la direction de chaque école active. */
class ResumeHebdomadaire extends Command
{
    protected $signature = 'resume:hebdomadaire';

    protected $description = 'Envoie à la direction le résumé de la semaine écoulée.';

    public function handle(): int
    {
        $debut = now()->subWeek()->startOfDay();
        $periode = $debut->format('d/m/Y').' au '.now()->subDay()->format('d/m/Y');
        $envoyes = 0;

        Etablissement::where('statut_abonnement', 'actif')->get()->each(function (Etablissement $etab) use ($debut, $periode, &$envoyes) {
            $destinataires = User::withoutGlobalScopes()
                ->where('etablissement_id', $etab->id)
                ->whereIn('role', ['admin', 'proviseur'])
                ->pluck('email');

            if ($destinataires->isEmpty()) {
                return;
            }

            $indicateurs = [
                'Absences enregistrées' => Absence::whereHas('etudiant', fn ($q) => $q->where('etablissement_id', $etab->id))
                    ->where('date', '>=', $debut->toDateString())->count(),
                'Notes saisies' => Note::withoutGlobalScopes()->where('etablissement_id', $etab->id)
                    ->where('created_at', '>=', $debut)->count(),
                'Nouveaux élèves inscrits' => Etudiant::withoutGlobalScopes()->where('etablissement_id', $etab->id)
                    ->where('created_at', '>=', $debut)->count(),
                'Montant encaissé (FCFA)' => (int) DB::table('paiements')
                    ->where('etablissement_id', $etab->id)->where('statut', 'completed')
                    ->where('created_at', '>=', $debut)->sum('montant'),
            ];

            foreach ($destinataires as $email) {
                try {
                    Mail::to($email)->queue(new ResumeMail($etab->nom, $periode, $indicateurs));
                    $envoyes++;
                } catch (\Throwable $e) {
                    // Un destinataire en échec n'empêche pas les autres.
                }
            }
        });

        $this->info("Résumés envoyés : {$envoyes}.");

        return self::SUCCESS;
    }
}
