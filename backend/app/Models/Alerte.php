<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alerte extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'etudiant_id',
        'type',
        'severity',
        'message',
        'acknowledged',
        'etablissement_id',
    ];

    protected $casts = [
        'acknowledged' => 'boolean',
    ];

    public function etudiant(): BelongsTo
    {
        return $this->belongsTo(Etudiant::class);
    }
}
