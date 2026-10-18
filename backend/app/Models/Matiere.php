<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Matiere extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'nom', 'code', 'coefficient', 'couleur', 'etablissement_id',
    ];

    public function enseignants(): BelongsToMany
    {
        return $this->belongsToMany(
            Enseignant::class,
            'enseignant_matiere',
            'matiere_id',
            'enseignant_id'
        )->withTimestamps();
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }
}
