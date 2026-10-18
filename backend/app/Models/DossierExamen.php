<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DossierExamen extends Model
{
    use HasFactory;

    protected $table = 'dossiers_examen';

    protected $fillable = [
        'etudiant_id',
        'examen',
        'acte_naissance',
        'acte_naissance_path',
        'photos_identite',
        'photos_identite_path',
        'fiche_renseignement',
        'fiche_renseignement_path',
        'paiement',
        'observations',
    ];

    protected $casts = [
        'acte_naissance' => 'boolean',
        'photos_identite' => 'boolean',
        'fiche_renseignement' => 'boolean',
        'paiement' => 'boolean',
    ];

    protected $appends = ['complet'];

    public function etudiant(): BelongsTo
    {
        return $this->belongsTo(Etudiant::class);
    }

    public function getCompletAttribute(): bool
    {
        return $this->acte_naissance
            && $this->photos_identite
            && $this->fiche_renseignement
            && $this->paiement;
    }
}
