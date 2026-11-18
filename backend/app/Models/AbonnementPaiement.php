<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Renouvellement d'abonnement enregistré par la plateforme (paiement hors ligne). */
class AbonnementPaiement extends Model
{
    protected $fillable = [
        'etablissement_id', 'plan', 'montant', 'mode_paiement', 'reference',
        'periode_debut', 'periode_fin', 'enregistre_par',
    ];

    protected $casts = [
        'periode_debut' => 'date',
        'periode_fin' => 'date',
    ];
}
