<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/** Notification envoyée à un parent (absence, retard, convocation). */
class NotificationParent extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $sujetMessage, public string $corps) {}

    public function build(): self
    {
        return $this->subject($this->sujetMessage)->html(nl2br(e($this->corps)));
    }
}
