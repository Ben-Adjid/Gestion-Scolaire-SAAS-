<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\ReinitialisationMotDePasse;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/** Mot de passe oublié, réinitialisation par e-mail, changement de mot de passe temporaire. */
class MotDePasseController extends Controller
{
    private const DUREE_MINUTES = 60;
    private const MESSAGE_GENERIQUE = 'Si un compte existe pour cette adresse, un lien de réinitialisation vient d’être envoyé.';

    public function oublie(Request $request): JsonResponse
    {
        $email = (string) $request->validate(['email' => 'required|email'])['email'];
        $compte = User::withoutGlobalScopes()->where('email', $email)->first();

        if ($compte) {
            $jeton = Str::random(64);
            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $compte->email],
                ['token' => Hash::make($jeton), 'created_at' => now()],
            );

            $lien = rtrim(config('app.frontend_url'), '/').'/reinitialiser?'.http_build_query(['token' => $jeton, 'email' => $compte->email]);
            try {
                Mail::to($compte->email)->send(new ReinitialisationMotDePasse($lien));
            } catch (\Throwable $e) {
                // L'envoi peut échouer : la réponse reste identique pour ne rien révéler.
            }
        }

        // Réponse identique, que le compte existe ou non.
        return response()->json(['message' => self::MESSAGE_GENERIQUE]);
    }

    /** Activation d'un compte d'établissement : l'administrateur choisit son mot de passe (jeton d'invitation, 72 h). */
    public function activer(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => ['required', 'confirmed', Password::min(8)],
            'accepte_cgu' => ['accepted'],
        ], [
            'accepte_cgu.accepted' => 'Vous devez accepter les conditions générales d’utilisation pour activer votre espace.',
        ]);

        $cle = 'activation:'.$donnees['email'];
        $ligne = DB::table('password_reset_tokens')->where('email', $cle)->first();
        $valide = $ligne
            && \Illuminate\Support\Carbon::parse($ligne->created_at)->greaterThan(now()->subHours(72))
            && Hash::check($donnees['token'], $ligne->token);

        if (! $valide) {
            return response()->json(['message' => 'Ce lien d’activation est invalide ou a expiré. Demandez à la plateforme de vous renvoyer l’invitation.'], 422);
        }

        $compte = User::withoutGlobalScopes()->where('email', $donnees['email'])->where('role', 'admin')->firstOrFail();
        $compte->forceFill([
            'password' => Hash::make($donnees['password']),
            'doit_changer_mot_de_passe' => false,
            'email_verified_at' => now(),
            'cgu_version' => config('cgu.version'),
            'cgu_acceptees_le' => now(),
        ])->save();
        $compte->tokens()->delete();
        DB::table('password_reset_tokens')->where('email', $cle)->delete();

        AuditLogger::log($compte, 'activation_compte', 'users', $compte->id, 'Compte administrateur activé par e-mail.');

        return response()->json(['message' => 'Compte activé. Vous pouvez vous connecter avec votre nouveau mot de passe.']);
    }

    public function reinitialiser(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $ligne = DB::table('password_reset_tokens')->where('email', $donnees['email'])->first();
        $valide = $ligne
            && \Illuminate\Support\Carbon::parse($ligne->created_at)->greaterThan(now()->subMinutes(self::DUREE_MINUTES))
            && Hash::check($donnees['token'], $ligne->token);

        if (! $valide) {
            return response()->json(['message' => 'Ce lien est invalide ou a expiré. Refaites une demande de réinitialisation.'], 422);
        }

        $compte = User::withoutGlobalScopes()->where('email', $donnees['email'])->firstOrFail();
        $compte->forceFill([
            'password' => Hash::make($donnees['password']),
            'doit_changer_mot_de_passe' => false,
        ])->save();
        $compte->tokens()->delete();
        DB::table('password_reset_tokens')->where('email', $donnees['email'])->delete();

        AuditLogger::log($compte, 'reinitialisation_mot_de_passe', 'users', $compte->id, 'Mot de passe réinitialisé par e-mail.');

        return response()->json(['message' => 'Mot de passe réinitialisé. Vous pouvez vous connecter.']);
    }

    public function changer(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'actuel' => 'required|string',
            'nouveau' => ['required', 'confirmed', 'different:actuel', Password::min(8)],
        ]);

        $compte = $request->user();
        if (! Hash::check($donnees['actuel'], $compte->password)) {
            return response()->json(['message' => 'Le mot de passe actuel est incorrect.'], 422);
        }

        $compte->forceFill([
            'password' => Hash::make($donnees['nouveau']),
            'doit_changer_mot_de_passe' => false,
        ])->save();

        AuditLogger::log($compte, 'changement_mot_de_passe', 'users', $compte->id, 'Mot de passe changé par l’utilisateur.');

        return response()->json(['message' => 'Votre mot de passe a été changé.']);
    }
}
