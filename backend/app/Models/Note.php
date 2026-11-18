<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Note extends Model
{
    use HasFactory;

    protected $fillable = [
        'etudiant_id',
        'matiere_id',
        'trimestre',
        'type',
        'valeur',
        'statut',
        'appreciation',
        'annee_scolaire',
    ];

    protected $casts = [
        'valeur' => 'float',
        'trimestre' => 'integer',
    ];

    protected static function booted(): void
    {
        // Sans année explicite, la note prend l'année en cours de l'établissement de l'élève.
        static::creating(function (Note $note) {
            if (blank($note->annee_scolaire) && $note->etudiant_id) {
                $note->annee_scolaire = Etudiant::withoutGlobalScopes()->with('etablissement')
                    ->find($note->etudiant_id)?->etablissement?->annee_scolaire;
            }
        });
    }

    public function etudiant(): BelongsTo
    {
        return $this->belongsTo(Etudiant::class);
    }

    public function matiere(): BelongsTo
    {
        return $this->belongsTo(Matiere::class);
    }
}
