<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FraisScolaire extends Model
{
    use BelongsToTenant;

    protected $table = 'frais_scolaires';

    protected $fillable = [
        'etudiant_id', 'libelle', 'montant', 'annee_scolaire',
        'date_echeance', 'statut', 'etablissement_id'
    ];

    protected $casts = [
        'montant' => 'decimal:2',
        'date_echeance' => 'date',
    ];

    protected $appends = ['montant_total', 'montant_paye', 'reste'];

    public function getMontantTotalAttribute(): float
    {
        return (float) $this->montant;
    }

    public function getMontantPayeAttribute(): float
    {
        return (float) $this->paiements()->where('statut', 'completed')->sum('montant');
    }

    public function getResteAttribute(): float
    {
        return $this->montant_total - $this->montant_paye;
    }

    public function etudiant(): BelongsTo
    {
        return $this->belongsTo(Etudiant::class);
    }

    public function paiements(): HasMany
    {
        return $this->hasMany(Paiement::class);
    }

    public function totalPaid()
    {
        return $this->paiements()->where('statut', 'completed')->sum('montant');
    }

    public function remainingBalance()
    {
        return $this->montant - $this->totalPaid();
    }
}
