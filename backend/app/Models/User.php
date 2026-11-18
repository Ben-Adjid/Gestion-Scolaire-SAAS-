<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

use App\Traits\BelongsToTenant;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, BelongsToTenant;

    public const ROLES = ['super_admin', 'admin', 'proviseur', 'censeur', 'cpe', 'surveillant', 'enseignant', 'secretaire', 'gestionnaire', 'agent_comptable', 'responsable_it', 'parent'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'enseignant_id',
        'affectation',
        'telephone',
        'etablissement_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'preferences_notifications' => 'array',
    ];

    /** Événements et canaux qu'un parent peut choisir de recevoir (tout est activé par défaut). */
    public const EVENEMENTS_NOTIFIES = ['absence', 'retard', 'convocation', 'sortie'];

    public const CANAUX_NOTIFICATION = ['email', 'whatsapp'];

    /** Préférences complètes : chaque événement × canal, avec « vrai » par défaut. */
    public function preferencesNotifications(): array
    {
        $enregistrees = $this->preferences_notifications ?? [];
        $resultat = [];
        foreach (self::EVENEMENTS_NOTIFIES as $evenement) {
            foreach (self::CANAUX_NOTIFICATION as $canal) {
                $resultat[$evenement][$canal] = (bool) ($enregistrees[$evenement][$canal] ?? true);
            }
        }

        return $resultat;
    }

    public function accepteNotification(string $evenement, string $canal): bool
    {
        return $this->preferencesNotifications()[$evenement][$canal] ?? true;
    }

    public function enseignant(): BelongsTo
    {
        return $this->belongsTo(Enseignant::class);
    }

    public function enfants(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Etudiant::class, 'parent_etudiant', 'user_id', 'etudiant_id')->withPivot('lien')->withTimestamps();
    }

    public function permissions(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(\App\Models\Permission::class, 'permission_user');
    }

    /** Administrateurs : tous les droits. Les autres comptes : seulement les permissions accordées. */
    public function hasPermission(string $code): bool
    {
        if (in_array($this->role, ['super_admin', 'admin'], true)) {
            return true;
        }

        return $this->permissions()->where('code', $code)->exists();
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function hasAnyRole(array $roles): bool
    {
        return $this->isSuperAdmin() || in_array($this->role, $roles, true);
    }
}
