<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Groupe scolaire : plusieurs établissements (sites) rattachés à un même propriétaire. */
class GroupeScolaire extends Model
{
    protected $table = 'groupes_scolaires';

    protected $fillable = ['nom'];

    public function etablissements(): HasMany
    {
        return $this->hasMany(Etablissement::class, 'groupe_scolaire_id');
    }
}
