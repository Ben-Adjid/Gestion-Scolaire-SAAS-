<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Dépense de l'établissement (salaires, fournitures, maintenance, transport, autres). */
class Depense extends Model
{
    use BelongsToTenant;

    public const CATEGORIES = ['salaires', 'fournitures', 'maintenance', 'transport', 'autre'];

    protected $fillable = ['etablissement_id', 'categorie', 'libelle', 'montant', 'date'];

    protected $casts = [
        'montant' => 'decimal:2',
        'date' => 'date:Y-m-d',
    ];
}
