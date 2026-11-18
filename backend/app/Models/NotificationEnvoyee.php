<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Trace d'une notification envoyée (ou échouée) : garantit qu'un événement n'est notifié qu'une fois. */
class NotificationEnvoyee extends Model
{
    protected $table = 'notifications_envoyees';

    protected $fillable = ['etablissement_id', 'destinataire_id', 'type', 'reference_id', 'canal', 'statut', 'erreur'];
}
