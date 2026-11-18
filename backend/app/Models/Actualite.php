<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Publication du fil d'actualité de l'établissement. */
class Actualite extends Model
{
    use BelongsToTenant;

    protected $table = 'actualites';

    protected $fillable = ['etablissement_id', 'auteur_id', 'titre', 'contenu'];

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auteur_id');
    }
}
