<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Enseignant extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'nom', 'prenom', 'email', 'telephone', 'specialite', 'code_pointe', 'etablissement_id',
    ];

    protected static function booted(): void
    {
        static::creating(function (Enseignant $enseignant) {
            if (blank($enseignant->code_pointe)) {
                $enseignant->code_pointe = self::generateCode();
            }
        });
    }

    public static function generateCode(): string
    {
        do {
            $code = 'P-'.strtoupper(Str::random(6));
        } while (static::where('code_pointe', $code)->exists());

        return $code;
    }

    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(
            Classe::class,
            'classe_enseignant',
            'enseignant_id',
            'classe_id'
        )->withTimestamps();
    }

    public function matieres(): BelongsToMany
    {
        return $this->belongsToMany(
            Matiere::class,
            'enseignant_matiere',
            'enseignant_id',
            'matiere_id'
        )->withTimestamps();
    }

    public function pointages(): HasMany
    {
        return $this->hasMany(Pointage::class);
    }

    public function absencesEnseignant(): HasMany
    {
        return $this->hasMany(AbsenceEnseignant::class);
    }
}
