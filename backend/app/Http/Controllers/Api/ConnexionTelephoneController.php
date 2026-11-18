<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Connexion par téléphone : un code à six chiffres est envoyé par WhatsApp au numéro enregistré.
 * - Réponse identique que le numéro existe ou non (pas d'énumération des comptes).
 * - Code valable 10 minutes, à usage unique, haché en base ; 5 essais maximum.
 * - Refusée pour les comptes avec double authentification (ils gardent leur code d'application).
 */
class ConnexionTelephoneController extends Controller
{
    private const DUREE_MINUTES = 10;
    private const ESSAIS_MAX = 5;
    private const MESSAGE = 'Si ce numéro est enregistré, un code de connexion vient d’être envoyé par WhatsApp.';

    public function demander(Request $request, WhatsAppService $whatsapp): JsonResponse
    {
        $numero = (string) $request->validate(['telephone' => 'required|string|max:30'])['telephone'];

        $compte = $this->trouverCompte($numero);
        if ($compte && ! $compte->two_factor_confirmed_at) {
            $code = (string) random_int(100000, 999999);
            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $this->cle($compte)],
                ['token' => Hash::make($code), 'created_at' => now()],
            );
            RateLimiter::clear($this->cleEssais($compte));

            if (! $whatsapp->envoyerCodeConnexion($compte->telephone, $code)) {
                Log::warning('Connexion par téléphone : WhatsApp indisponible, code non envoyé.');
            }
        }

        return response()->json(['message' => self::MESSAGE]);
    }

    public function verifier(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'telephone' => 'required|string|max:30',
            'code' => 'required|digits:6',
        ]);

        $compte = $this->trouverCompte($donnees['telephone']);
        if (! $compte) {
            throw ValidationException::withMessages(['code' => ['Code invalide ou expiré.']]);
        }

        if (RateLimiter::tooManyAttempts($this->cleEssais($compte), self::ESSAIS_MAX)) {
            DB::table('password_reset_tokens')->where('email', $this->cle($compte))->delete();

            return response()->json(['message' => 'Trop d’essais. Demandez un nouveau code.', 'code' => 'trop_essais'], 429);
        }

        $ligne = DB::table('password_reset_tokens')->where('email', $this->cle($compte))->first();
        $valide = $ligne
            && \Illuminate\Support\Carbon::parse($ligne->created_at)->greaterThan(now()->subMinutes(self::DUREE_MINUTES))
            && Hash::check($donnees['code'], $ligne->token);

        if (! $valide) {
            RateLimiter::hit($this->cleEssais($compte), 900);
            throw ValidationException::withMessages(['code' => ['Code invalide ou expiré.']]);
        }

        // Code utilisé : il ne sert plus.
        DB::table('password_reset_tokens')->where('email', $this->cle($compte))->delete();
        RateLimiter::clear($this->cleEssais($compte));

        AuditLogger::log($compte, 'connexion', 'authentification', null, "Connexion par téléphone de {$compte->name} ({$compte->role}).");

        return response()->json([
            'token' => $compte->createToken('auth-token')->plainTextToken,
            'user' => $compte,
        ]);
    }

    /** Compte dont le téléphone correspond (comparaison sur les 8 derniers chiffres), hors plateforme. */
    private function trouverCompte(string $numero): ?User
    {
        $chiffres = preg_replace('/\D+/', '', $numero);
        if (strlen($chiffres) < 8) {
            return null;
        }
        $fin = substr($chiffres, -8);

        return User::withoutGlobalScopes()
            ->where('role', '!=', 'super_admin')
            ->whereNotNull('telephone')
            // Les espaces, tirets et signes sont ignorés : « +235 66 12 34 56 » = « 66123456 ».
            ->whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(telephone, ' ', ''), '-', ''), '+', ''), '.', '') LIKE ?", ['%'.$fin])
            ->get()
            ->first(fn (User $u) => substr(preg_replace('/\D+/', '', (string) $u->telephone), -8) === $fin
                && strlen(preg_replace('/\D+/', '', (string) $u->telephone)) >= 8);
    }

    private function cle(User $compte): string
    {
        return 'otp:'.$compte->id;
    }

    private function cleEssais(User $compte): string
    {
        return 'otp-essais:'.$compte->id;
    }
}
