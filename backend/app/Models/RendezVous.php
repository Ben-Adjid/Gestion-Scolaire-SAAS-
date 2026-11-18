<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Rendez-vous demandé par un parent à un enseignant de son enfant. */
class RendezVous extends Model
{
    use BelongsToTenant;

    protected $table = 'rendez_vous';

    public const STATUTS = ['demande', 'confirme', 'refuse', 'annule'];

    protected $fillable = [
        'etablissement_id', 'etudiant_id', 'parent_id', 'enseignant_id', 'motif',
        'creneaux_proposes', 'date_retenue', 'lieu', 'statut', 'reponse', 'traite_par',
    ];

    protected $casts = [
        'creneaux_proposes' => 'array',
        'date_retenue' => 'datetime',
    ];

    public function etudiant(): BelongsTo
    {
        return $this->belongsTo(Etudiant::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_id');
    }

    public function enseignant(): BelongsTo
    {
        return $this->belongsTo(Enseignant::class);
    }
}
