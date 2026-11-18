<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Paiement en ligne d'un frais scolaire, initié par un parent auprès du prestataire de l'école. */
class TransactionEnLigne extends Model
{
    use BelongsToTenant;

    protected $table = 'transactions_en_ligne';

    protected $fillable = [
        'etablissement_id', 'frais_scolaire_id', 'etudiant_id', 'parent_id', 'reference', 'montant', 'devise',
        'prestataire', 'statut', 'url_paiement', 'reponse_prestataire', 'paiement_id',
    ];

    protected $casts = [
        'reponse_prestataire' => 'array',
        'montant' => 'integer',
    ];

    protected $hidden = ['reponse_prestataire'];

    public function frais(): BelongsTo
    {
        return $this->belongsTo(FraisScolaire::class, 'frais_scolaire_id');
    }

    public function etudiant(): BelongsTo
    {
        return $this->belongsTo(Etudiant::class);
    }
}
