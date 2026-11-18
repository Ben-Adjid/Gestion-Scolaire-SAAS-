<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Envoi de messages WhatsApp via la WhatsApp Cloud API (Meta).
 * Sans identifiants configurés (WHATSAPP_TOKEN, WHATSAPP_PHONE_ID), rien n'est envoyé :
 * l'événement est seulement journalisé, et la relance sera retentée au passage suivant.
 */
class WhatsAppService
{
    public function envoyer(string $numero, string $message): bool
    {
        $token = config('services.whatsapp.token');
        $phoneId = config('services.whatsapp.phone_id');

        if (! $token || ! $phoneId) {
            Log::info('WhatsApp non configuré : message non envoyé.', ['destinataire' => $numero]);

            return false;
        }

        $reponse = Http::withToken($token)
            ->timeout(10)
            ->post("https://graph.facebook.com/v20.0/{$phoneId}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => preg_replace('/\D+/', '', $numero),
                'type' => 'text',
                'text' => ['body' => $message],
            ]);

        if (! $reponse->successful()) {
            Log::warning('WhatsApp : échec d\'envoi.', ['statut' => $reponse->status()]);
        }

        return $reponse->successful();
    }

    /**
     * Message d'authentification (code de connexion) : WhatsApp n'accepte un premier message
     * que s'il s'agit d'un modèle approuvé par Meta. Le nom du modèle est configuré (WHATSAPP_OTP_TEMPLATE).
     */
    public function envoyerCodeConnexion(string $numero, string $code): bool
    {
        $token = config('services.whatsapp.token');
        $phoneId = config('services.whatsapp.phone_id');
        $modele = config('services.whatsapp.otp_template');

        if (! $token || ! $phoneId || ! $modele) {
            Log::info('WhatsApp non configuré : code de connexion non envoyé.');

            return false;
        }

        $reponse = Http::withToken($token)
            ->timeout(10)
            ->post("https://graph.facebook.com/v20.0/{$phoneId}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => preg_replace('/\D+/', '', $numero),
                'type' => 'template',
                'template' => [
                    'name' => $modele,
                    'language' => ['code' => config('services.whatsapp.otp_langue', 'fr')],
                    'components' => [
                        ['type' => 'body', 'parameters' => [['type' => 'text', 'text' => $code]]],
                        ['type' => 'button', 'sub_type' => 'url', 'index' => '0', 'parameters' => [['type' => 'text', 'text' => $code]]],
                    ],
                ],
            ]);

        return $reponse->successful();
    }
}
