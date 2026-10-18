<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Etablissement extends Model
{
    use HasFactory;

    protected $fillable = [
        'nom',
        'devise',
        'ministere',
        'adresse',
        'telephone',
        'responsable',
        'annee_scolaire',
        'prefixe_matricule',
        'logo_path',
        'latitude',
        'longitude',
        'rayon_pointage',
        // Champs SaaS
        'plan',
        'statut_abonnement',
        'abonnement_fin',
    ];

    protected $casts = [
        'latitude'       => 'float',
        'longitude'      => 'float',
        'rayon_pointage' => 'integer',
        'abonnement_fin' => 'date',
    ];

    // ── Relations ──────────────────────────────────────

    public function classes(): HasMany
    {
        return $this->hasMany(Classe::class)->withoutGlobalScopes();
    }

    public function etudiants(): HasMany
    {
        return $this->hasMany(Etudiant::class)->withoutGlobalScopes();
    }

    public function enseignants(): HasMany
    {
        return $this->hasMany(Enseignant::class)->withoutGlobalScopes();
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class)->withoutGlobalScopes();
    }

    // ── Helpers ────────────────────────────────────────

    /** Vérifie si l'abonnement est actif. */
    public function isActif(): bool
    {
        return in_array($this->statut_abonnement, ['actif', 'essai'], true)
            && (! $this->abonnement_fin || ! $this->abonnement_fin->isBefore(today()));
    }

    /** Retourne le libellé du plan. */
    public function getPlanLabelAttribute(): string
    {
        return match($this->plan) {
            'starter'    => 'Starter',
            'standard'   => 'Standard',
            'premium'    => 'Premium',
            'entreprise' => 'Entreprise',
            default      => 'Standard',
        };
    }
}
