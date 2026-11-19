<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Paiement extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'frais_scolaire_id', 'etudiant_id', 'montant', 'date_paiement',
        'mode_paiement', 'reference', 'statut', 'notes', 'recu_url', 'etablissement_id', 'enregistre_par', 'payeur_nom'
    ];

    /** Nom du payeur affiché sur le reçu : celui saisi, sinon le nom du tuteur de l'élève. */
    protected $appends = ['payeur_affiche'];

    protected $casts = [
        'montant' => 'decimal:2',
        'date_paiement' => 'date',
    ];

    public function fraisScolaire(): BelongsTo
    {
        return $this->belongsTo(FraisScolaire::class);
    }

    public function etudiant(): BelongsTo
    {
        return $this->belongsTo(Etudiant::class);
    }

    public function getPayeurAfficheAttribute(): ?string
    {
        if (filled($this->payeur_nom)) {
            return $this->payeur_nom;
        }

        return $this->etudiant?->parents()->orderBy('users.id')->first()?->name;
    }

    /** Agent de caisse ayant enregistré l'encaissement (absent sur les anciens paiements). */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enregistre_par');
    }
}
