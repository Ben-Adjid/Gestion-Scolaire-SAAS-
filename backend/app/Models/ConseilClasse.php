<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConseilClasse extends Model
{
    use \App\Traits\BelongsToTenant;

    protected $table = 'conseils_classe';

    protected $fillable = [
        'classe_id', 'trimestre', 'annee_scolaire', 'date_conseil',
        'pv', 'participants', 'pdf_url', 'etablissement_id'
    ];

    protected $casts = [
        'date_conseil' => 'date',
        'participants' => 'array',
    ];

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }
}
