<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Regle extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'seuil_admission',
        'seuil_bien',
        'seuil_tres_bien',
        'seuil_excellence',
        'nb_tableau_honneur',
        'etablissement_id',
    ];

    protected $casts = [
        'seuil_admission' => 'float',
        'seuil_bien' => 'float',
        'seuil_tres_bien' => 'float',
        'seuil_excellence' => 'float',
    ];
}
