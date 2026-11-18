<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Entrée du cahier de texte : contenu du cours et devoirs, pour une classe et une matière. */
class CahierTexte extends Model
{
    use BelongsToTenant;

    protected $table = 'cahiers_texte';

    protected $fillable = ['etablissement_id', 'classe_id', 'matiere_id', 'enseignant_id', 'date', 'contenu', 'devoirs'];

    protected $casts = [
        'date' => 'date',
    ];

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }

    public function matiere(): BelongsTo
    {
        return $this->belongsTo(Matiere::class);
    }

    public function enseignant(): BelongsTo
    {
        return $this->belongsTo(Enseignant::class);
    }
}
