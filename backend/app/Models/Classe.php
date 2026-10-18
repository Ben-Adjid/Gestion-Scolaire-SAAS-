<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Classe extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'classes';

    protected $fillable = [
        'nom', 'niveau', 'description', 'effectif_max', 'cycle', 'etablissement_id',
    ];

    public function etudiants(): HasMany
    {
        return $this->hasMany(Etudiant::class);
    }

    public function inscriptions(): HasMany
    {
        return $this->hasMany(Inscription::class);
    }

    public function enseignants(): BelongsToMany
    {
        return $this->belongsToMany(
            Enseignant::class,
            'classe_enseignant',
            'classe_id',
            'enseignant_id'
        )->withTimestamps();
    }

    public function notes(): HasManyThrough
    {
        return $this->hasManyThrough(Note::class, Etudiant::class);
    }
}
