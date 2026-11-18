<?php

namespace App\Services;

use App\Models\Absence;
use App\Models\Etablissement;
use App\Models\Note;
use Illuminate\Support\Facades\DB;

/**
 * Score de santé d'une école (0 à 100), pour anticiper les départs.
 * Quatre critères de 25 points : saisie des notes (30 j), appels (30 j), paiements (60 j), abonnement non proche de l'échéance.
 */
class ScoreSante
{
    public static function pour(Etablissement $etab): array
    {
        $notes = Note::withoutGlobalScopes()->where('etablissement_id', $etab->id)
            ->where('created_at', '>=', now()->subDays(30))->exists();

        $appels = Absence::whereHas('etudiant', fn ($q) => $q->where('etablissement_id', $etab->id))
            ->where('date', '>=', now()->subDays(30)->toDateString())->exists();

        $paiements = DB::table('paiements')->where('etablissement_id', $etab->id)
            ->where('statut', 'completed')->where('created_at', '>=', now()->subDays(60))->exists();

        $jours = $etab->jours_restants;
        $abonnement = $etab->statut_abonnement === 'actif' && ($jours === null || $jours >= 7);

        $criteres = [
            ['libelle' => 'Notes saisies (30 derniers jours)', 'ok' => $notes],
            ['libelle' => 'Appels effectués (30 derniers jours)', 'ok' => $appels],
            ['libelle' => 'Paiements reçus (60 derniers jours)', 'ok' => $paiements],
            ['libelle' => 'Abonnement actif, échéance non imminente', 'ok' => $abonnement],
        ];

        return [
            'score' => count(array_filter($criteres, fn ($c) => $c['ok'])) * 25,
            'criteres' => $criteres,
        ];
    }
}
