<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

use App\Traits\BelongsToTenant;

class Etudiant extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'matricule',
        'nom',
        'prenom',
        'date_naissance',
        'genre',
        'email',
        'telephone',
        'adresse',
        'classe_id',
        'annee_scolaire',
        'etablissement_id',
    ];

    protected $casts = [
        'date_naissance' => 'date:Y-m-d',
    ];

    protected static function booted(): void
    {
        static::creating(function (Etudiant $etudiant) {
            if (blank($etudiant->matricule)) {
                $etudiant->matricule = self::generateMatricule($etudiant->etablissement_id);
            }
        });
    }

    public static function generateMatricule($etablissementId = null): string
    {
        $tenantId = $etablissementId ?: auth()->user()?->etablissement_id
            ?: Etablissement::query()->latest('id')->value('id');
        $prefix = (string) (Etablissement::where('id', $tenantId)->value('prefixe_matricule') ?: 'LIC');
        $year = now()->format('Y');

        $last = static::where('matricule', 'like', $prefix.'-'.$year.'-%')
            ->orderByDesc('matricule')
            ->value('matricule');

        $next = 1;

        if ($last && preg_match('/-(\d+)$/', $last, $matches)) {
            $next = (int) $matches[1] + 1;
        }

        do {
            $matricule = sprintf('%s-%s-%04d', $prefix, $year, $next);
            $next++;
        } while (static::where('matricule', $matricule)->exists());

        return $matricule;
    }

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    public function inscriptions(): HasMany
    {
        return $this->hasMany(Inscription::class);
    }

    public function dossierExamen(): HasOne
    {
        return $this->hasOne(DossierExamen::class);
    }

    public function dossierInscription(): HasOne
    {
        return $this->hasOne(DossierInscription::class);
    }

    public function fraisScolaires(): HasMany
    {
        return $this->hasMany(FraisScolaire::class);
    }

    public function convocations(): HasMany
    {
        return $this->hasMany(Convocation::class);
    }

    public function absences(): HasMany
    {
        return $this->hasMany(Absence::class);
    }
}
