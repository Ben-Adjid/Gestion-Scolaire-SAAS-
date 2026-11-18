<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Etablissement extends Model
{
    use HasFactory;

    protected $appends = ['plan_label', 'fonctionnalites', 'jours_restants', 'pays_info'];

    /**
     * Jours avant la fin de l'abonnement (négatif si dépassé, null si pas de date).
     * Sert au bandeau d'alerte du tableau de bord.
     */
    public function getJoursRestantsAttribute(): ?int
    {
        return $this->abonnement_fin ? (int) today()->diffInDays($this->abonnement_fin, false) : null;
    }

    protected $fillable = [
        'groupe_scolaire_id',
        'nom',
        'pays',
        'devise',
        'ministere',
        'adresse',
        'telephone',
        'responsable',
        'annee_scolaire',
        'prefixe_matricule',
        'bulletin_signature_nom',
        'bulletin_signature_titre',
        'bulletin_mention',
        'bulletin_afficher_rang',
        'bulletin_couleur',
        'logo_path',
        'couleur_principale',
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
        'bulletin_afficher_rang' => 'boolean',
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
        return config('plans.plans.'.$this->planCode().'.libelle', 'Standard');
    }

    /** Nombre maximal d'utilisateurs de la formule (null = illimité). */
    public function maxUtilisateurs(): ?int
    {
        return config('plans.plans.'.$this->planCode().'.max_utilisateurs');
    }

    public function planCode(): string
    {
        return array_key_exists((string) $this->plan, config('plans.plans', []))
            ? (string) $this->plan
            : config('plans.defaut');
    }

    /** Code pays reconnu (catalogue config/pays.php), sinon le pays par défaut. */
    public function paysCode(): string
    {
        return array_key_exists((string) $this->pays, config('pays.liste', []))
            ? (string) $this->pays
            : config('pays.defaut');
    }

    /** En-tête officiel, devise nationale, monnaie et indicatif du pays de l'école. */
    public function getPaysInfoAttribute(): array
    {
        return ['code' => $this->paysCode()] + config('pays.liste.'.$this->paysCode(), []);
    }

    /** Symbole de la monnaie de l'école (FCFA, GNF, DH…), pour les montants des élèves et des dépenses. */
    public function monnaie(): string
    {
        return $this->pays_info['monnaie'] ?? 'FCFA';
    }

    /** Fonctionnalités incluses dans le plan de l'établissement. */
    public function fonctionnalites(): array
    {
        return config('plans.plans.'.$this->planCode().'.fonctionnalites', []);
    }

    public function getFonctionnalitesAttribute(): array
    {
        return $this->fonctionnalites();
    }
}
