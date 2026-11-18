<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Archive annuelle scellée : instantané figé des données d'une année, avec son empreinte SHA-256.
 * Une archive n'est jamais modifiée ni supprimée (aucune route de modification n'existe).
 */
class ArchiveAnnee extends Model
{
    use BelongsToTenant;

    protected $table = 'archives_annee';

    public function auteur(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'archive_par');
    }

    protected $fillable = ['etablissement_id', 'annee_scolaire', 'donnees', 'empreinte', 'archive_par'];
}
