<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Événement du calendrier scolaire : rentrée, congés, examens, réunions. */
class EvenementCalendrier extends Model
{
    use BelongsToTenant;

    public const TYPES = ['rentree', 'conge', 'examen', 'reunion', 'autre'];

    protected $table = 'evenements_calendrier';

    protected $fillable = ['etablissement_id', 'titre', 'type', 'date_debut', 'date_fin', 'description'];

    protected $casts = [
        'date_debut' => 'date:Y-m-d',
        'date_fin' => 'date:Y-m-d',
    ];
}
