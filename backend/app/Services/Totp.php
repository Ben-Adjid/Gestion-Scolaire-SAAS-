<?php

namespace App\Services;

/**
 * Codes à usage unique basés sur le temps (RFC 6238, compatibles Google Authenticator, Microsoft Authenticator, Authy…).
 * Pas de dépendance externe : HMAC-SHA1, pas de 30 secondes, 6 chiffres, tolérance de ±1 pas d'horloge.
 */
class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function genererSecret(): string
    {
        $secret = '';
        for ($i = 0; $i < 20; $i++) {
            $secret .= self::ALPHABET[random_int(0, 31)];
        }

        return $secret;
    }

    public static function code(string $secret, ?int $instant = null): string
    {
        $pas = intdiv($instant ?? time(), 30);
        $hash = hash_hmac('sha1', pack('J', $pas), self::decoder($secret), true);

        $decalage = ord($hash[19]) & 0x0F;
        $valeur = ((ord($hash[$decalage]) & 0x7F) << 24)
            | ((ord($hash[$decalage + 1]) & 0xFF) << 16)
            | ((ord($hash[$decalage + 2]) & 0xFF) << 8)
            | (ord($hash[$decalage + 3]) & 0xFF);

        return str_pad((string) ($valeur % 1000000), 6, '0', STR_PAD_LEFT);
    }

    public static function verifier(string $secret, string $code): bool
    {
        $code = preg_replace('/\s+/', '', $code);
        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        foreach ([-30, 0, 30] as $decalage) {
            if (hash_equals(self::code($secret, time() + $decalage), $code)) {
                return true;
            }
        }

        return false;
    }

    /** Adresse à transformer en QR code par l'application d'authentification. */
    public static function uri(string $secret, string $emetteur, string $compte): string
    {
        return 'otpauth://totp/'.rawurlencode($emetteur.' : '.$compte)
            .'?secret='.$secret.'&issuer='.rawurlencode($emetteur).'&digits=6&period=30';
    }

    private static function decoder(string $base32): string
    {
        $bits = '';
        foreach (str_split(strtoupper($base32)) as $caractere) {
            $position = strpos(self::ALPHABET, $caractere);
            if ($position !== false) {
                $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
            }
        }

        $octets = '';
        foreach (str_split($bits, 8) as $octet) {
            if (strlen($octet) === 8) {
                $octets .= chr(bindec($octet));
            }
        }

        return $octets;
    }
}
