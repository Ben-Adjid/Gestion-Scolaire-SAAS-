<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmploiDuTemps extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'classe_id',
        'enseignant_id',
        'matiere_id',
        'creneau_id',
        'jour',
        'salle',
        'etablissement_id',
    ];

    protected $appends = ['heure_debut', 'heure_fin'];

    public function getHeureDebutAttribute(): ?string
    {
        return $this->creneau ? substr((string) $this->creneau->getRawOriginal('heure_debut'), 0, 5) : null;
    }

    public function getHeureFinAttribute(): ?string
    {
        return $this->creneau ? substr((string) $this->creneau->getRawOriginal('heure_fin'), 0, 5) : null;
    }

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }

    public function enseignant(): BelongsTo
    {
        return $this->belongsTo(Enseignant::class);
    }

    public function matiere(): BelongsTo
    {
        return $this->belongsTo(Matiere::class);
    }

    public function creneau(): BelongsTo
    {
        return $this->belongsTo(Creneau::class);
    }
}
