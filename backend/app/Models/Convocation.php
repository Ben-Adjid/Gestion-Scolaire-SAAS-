<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Convocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'etudiant_id',
        'date',
        'motif',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
    ];

    public function etudiant(): BelongsTo
    {
        return $this->belongsTo(Etudiant::class);
    }
}
