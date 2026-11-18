<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Événement de paiement reçu du prestataire (journal idempotent). */
class EvenementPaiement extends Model
{
    protected $table = 'evenements_paiement';

    protected $fillable = ['reference', 'etablissement_id', 'statut', 'charge'];

    protected $casts = [
        'charge' => 'array',
    ];
}
