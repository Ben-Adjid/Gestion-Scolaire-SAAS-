<?php

namespace App\Services;

use App\Mail\ActivationCompte;
use App\Models\Etablissement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Invitation d'activation d'un administrateur d'établissement.
 * Un jeton à usage unique (haché en base), valable 72 heures, envoyé par e-mail avec un lien
 * vers la page « Activer mon compte ». Le mot de passe est choisi par l'administrateur lui-même.
 */
class ActivationAdmin
{
    public function envoyer(User $admin, Etablissement $etablissement): void
    {
        $jeton = Str::random(64);
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => 'activation:'.$admin->email],
            ['token' => Hash::make($jeton), 'created_at' => now()],
        );

        $lien = rtrim((string) config('app.frontend_url'), '/').'/reinitialiser?'.http_build_query([
            'token' => $jeton,
            'email' => $admin->email,
            'activation' => 1,
        ]);

        Mail::to($admin->email)->send(new ActivationCompte($lien, $etablissement->nom, $admin->name));
    }
}
