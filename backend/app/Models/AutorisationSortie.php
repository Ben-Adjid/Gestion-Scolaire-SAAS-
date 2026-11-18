<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Autorisation de sortie anticipée, signée électroniquement par un parent. */
class AutorisationSortie extends Model
{
    use BelongsToTenant;

    protected $table = 'autorisations_sortie';

    public const STATUTS = ['en_attente', 'validee', 'refusee', 'effectuee', 'annulee'];

    protected $fillable = [
        'etablissement_id', 'etudiant_id', 'parent_id', 'date_sortie', 'heure_sortie', 'motif', 'accompagnant',
        'signature_nom', 'signe_le', 'signature_ip', 'signature_empreinte',
        'statut', 'traite_par', 'traite_le', 'commentaire', 'sortie_le',
    ];

    protected $casts = [
        'date_sortie' => 'date:Y-m-d',
        'signe_le' => 'datetime',
        'traite_le' => 'datetime',
        'sortie_le' => 'datetime',
    ];

    protected $hidden = ['signature_ip'];

    public function etudiant(): BelongsTo
    {
        return $this->belongsTo(Etudiant::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_id');
    }

    public function traitePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'traite_par');
    }

    /** Empreinte SHA-256 de ce qui a été signé : toute modification ultérieure du contenu se voit. */
    public static function empreinte(array $contenu): string
    {
        return hash('sha256', implode('|', [
            $contenu['etudiant_id'], $contenu['parent_id'], $contenu['date_sortie'], $contenu['heure_sortie'],
            $contenu['motif'], $contenu['accompagnant'] ?? '', $contenu['signature_nom'], $contenu['signe_le'],
        ]));
    }

    public function empreinteValide(): bool
    {
        return hash_equals($this->signature_empreinte, self::empreinte([
            'etudiant_id' => $this->etudiant_id,
            'parent_id' => $this->parent_id,
            'date_sortie' => $this->date_sortie->toDateString(),
            'heure_sortie' => substr((string) $this->heure_sortie, 0, 5),
            'motif' => $this->motif,
            'accompagnant' => $this->accompagnant,
            'signature_nom' => $this->signature_nom,
            'signe_le' => $this->signe_le->toIso8601String(),
        ]));
    }
}
