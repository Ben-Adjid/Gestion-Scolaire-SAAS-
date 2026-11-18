<?php

namespace App\Services;

use App\Models\AbonnementPaiement;
use App\Models\Etablissement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Cycle de vie de l'abonnement : activation après paiement, suspension, historique.
 * Point unique utilisé par la console super admin et par le webhook de paiement.
 */
class AbonnementService
{
    /**
     * Prolonge l'abonnement et active l'établissement. Si la période en cours court encore,
     * la nouvelle période part de sa date de fin.
     */
    public function renouveler(
        Etablissement $etablissement,
        string $plan,
        int $dureeMois,
        int $montant,
        string $modePaiement,
        ?string $reference = null,
        ?User $enregistrePar = null,
    ): AbonnementPaiement {
        $debut = $etablissement->abonnement_fin && $etablissement->abonnement_fin->isFuture()
            ? $etablissement->abonnement_fin->copy()
            : today();
        $fin = $debut->copy()->addMonths($dureeMois);

        return DB::transaction(function () use ($etablissement, $plan, $montant, $modePaiement, $reference, $enregistrePar, $debut, $fin) {
            $etablissement->update([
                'plan' => $plan,
                'statut_abonnement' => 'actif',
                'abonnement_fin' => $fin,
            ]);

            AuditLogger::log($enregistrePar, 'renouvellement_abonnement', 'SuperAdmin', $etablissement->id,
                "Abonnement {$plan} renouvelé jusqu'au {$fin->format('d/m/Y')} ({$montant} FCFA)");

            return AbonnementPaiement::create([
                'etablissement_id' => $etablissement->id,
                'plan' => $plan,
                'montant' => $montant,
                'mode_paiement' => $modePaiement,
                'reference' => $reference,
                'periode_debut' => $debut,
                'periode_fin' => $fin,
                'enregistre_par' => $enregistrePar?->id,
            ]);
        });
    }

    /** Suspend immédiatement l'établissement : ses utilisateurs ne peuvent plus accéder à l'espace. */
    public function suspendre(Etablissement $etablissement, string $motif): void
    {
        $etablissement->update(['statut_abonnement' => 'suspendu']);

        AuditLogger::log(null, 'suspension_abonnement', 'Etablissement', $etablissement->id, $motif);
    }
}
