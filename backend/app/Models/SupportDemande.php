<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Demande de support prioritaire émise par un établissement. */
class SupportDemande extends Model
{
    public const STATUTS = ['ouverte', 'en_cours', 'resolue'];

    protected $fillable = ['etablissement_id', 'user_id', 'sujet', 'message', 'statut', 'reponse'];

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function etablissement(): BelongsTo
    {
        return $this->belongsTo(Etablissement::class);
    }
}
