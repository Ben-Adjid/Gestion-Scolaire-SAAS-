<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'sender_id', 'recipient_id', 'class_id', 'subject', 'body',
        'type', 'read', 'read_at', 'etablissement_id', 'fichier_path', 'fichier_nom'
    ];

    protected $hidden = ['fichier_path'];

    protected $casts = [
        'read' => 'boolean',
        'read_at' => 'datetime',
    ];

    protected $appends = ['titre', 'contenu'];

    public function getTitreAttribute(): string
    {
        return (string) $this->subject;
    }

    public function getContenuAttribute(): string
    {
        return (string) $this->body;
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class, 'class_id');
    }

    public function markAsRead()
    {
        $this->update(['read' => true, 'read_at' => now()]);
        return $this;
    }
}
