<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbsenceEnseignant extends Model
{
    use HasFactory;

    protected $table = 'absences_enseignants';

    protected $fillable = [
        'enseignant_id',
        'date',
        'type',
        'motif',
        'justifie',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'justifie' => 'boolean',
    ];

    public function enseignant(): BelongsTo
    {
        return $this->belongsTo(Enseignant::class);
    }
}
