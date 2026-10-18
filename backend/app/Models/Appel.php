<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appel extends Model
{
    use HasFactory;

    protected $fillable = [
        'classe_id',
        'date',
        'user_id',
        'statut',
        'valide_par',
        'valide_at',
        'confirme_par',
        'confirme_at',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'valide_at' => 'datetime',
        'confirme_at' => 'datetime',
    ];

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function validePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'valide_par');
    }

    public function confirmePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirme_par');
    }

    public function getConfirmeAttribute(): bool
    {
        return $this->confirme_at !== null;
    }
}
