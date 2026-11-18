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
        'nom', 'niveau', 'description', 'effectif_max', 'cycle', 'batiment', 'etablissement_id',
    ];

    public const NIVEAUX_COLLEGE = ['6ème', '5ème', '4ème', '3ème'];

    /** Cycle déduit du niveau : collège (6e à 3e) ou lycée. */
    public static function cyclePourNiveau(?string $niveau): string
    {
        return in_array($niveau, self::NIVEAUX_COLLEGE, true) ? 'college' : 'lycee';
    }

    protected static function booted(): void
    {
        static::saving(function (Classe $classe) {
            if (blank($classe->cycle)) {
                $classe->cycle = self::cyclePourNiveau($classe->niveau);
            }
        });
    }

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
