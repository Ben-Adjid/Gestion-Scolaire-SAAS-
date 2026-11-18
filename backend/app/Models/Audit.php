<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Audit extends Model
{
    protected $fillable = [
        'user_id',
        'etablissement_id',
        'action',
        'module',
        'entite_id',
        'description',
        'ip',
        'valeurs_avant',
        'valeurs_apres',
    ];

    protected $casts = [
        'valeurs_avant' => 'array',
        'valeurs_apres' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
