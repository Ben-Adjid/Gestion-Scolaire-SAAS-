<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Une épreuve planifiée pour une classe et une matière. */
class ExamenPlanning extends Model
{
    use BelongsToTenant;

    protected $table = 'examens_planning';

    protected $fillable = ['etablissement_id', 'classe_id', 'matiere_id', 'date', 'heure_debut', 'duree_minutes', 'salle'];

    protected $casts = [
        'date' => 'date:Y-m-d',
    ];

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }

    public function matiere(): BelongsTo
    {
        return $this->belongsTo(Matiere::class);
    }
}
