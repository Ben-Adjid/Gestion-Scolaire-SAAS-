<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbsenceJustification extends Model
{
    use BelongsToTenant;

    protected $table = 'justifications_absence';

    protected $fillable = [
        'absence_id', 'demandeur_id', 'motif', 'statut', 'decide_par', 'decide_le', 'commentaire_decision', 'etablissement_id',
    ];

    protected $casts = [
        'decide_le' => 'datetime',
    ];

    public function absence(): BelongsTo
    {
        return $this->belongsTo(Absence::class);
    }

    public function demandeur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'demandeur_id');
    }
}
