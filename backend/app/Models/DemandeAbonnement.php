<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DemandeAbonnement extends Model
{
    public const STATUTS = ['en_attente', 'approuvee', 'refusee'];

    protected $table = 'demandes_abonnement';

    protected $fillable = [
        'etablissement_id',
        'user_id',
        'plan',
        'duree_mois',
        'montant',
        'mode_paiement',
        'reference',
        'justificatif_path',
        'statut',
        'traitee_par',
        'motif_refus',
        'abonnement_paiement_id',
    ];

    protected $hidden = ['justificatif_path'];

    public function etablissement(): BelongsTo
    {
        return $this->belongsTo(Etablissement::class);
    }

    public function demandeur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function traiteePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'traitee_par');
    }
}
