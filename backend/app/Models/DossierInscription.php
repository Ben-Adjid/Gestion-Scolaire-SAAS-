<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DossierInscription extends Model
{
    use HasFactory;

    protected $table = 'dossiers_inscription';

    protected $fillable = [
        'etudiant_id',
        'acte_naissance',
        'acte_naissance_path',
        'bulletin',
        'bulletin_path',
        'photo',
        'photo_path',
        'observations',
    ];

    protected $casts = [
        'acte_naissance' => 'boolean',
        'bulletin' => 'boolean',
        'photo' => 'boolean',
    ];

    protected $appends = ['complet'];

    public function etudiant(): BelongsTo
    {
        return $this->belongsTo(Etudiant::class);
    }

    public function getCompletAttribute(): bool
    {
        return $this->acte_naissance && $this->bulletin && $this->photo;
    }
}
