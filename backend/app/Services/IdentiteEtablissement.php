<?php

namespace App\Services;

use App\Models\Etablissement;

/**
 * Identité visuelle d'une école pour les documents (reçus, factures…) : nom, devise, coordonnées,
 * logo intégré en base64 (le document reste lisible hors ligne) et couleur de l'école.
 */
class IdentiteEtablissement
{
    public const COULEUR_DEFAUT = '#1F5C4A';

    public static function pour(?Etablissement $etablissement): array
    {
        $couleur = self::couleur($etablissement);

        return [
            'nom' => $etablissement?->nom ?? 'Établissement',
            'devise' => $etablissement?->devise,
            'adresse' => $etablissement?->adresse,
            'ville' => $etablissement?->ville,
            'pays' => $etablissement?->pays_info['nom'] ?? null,
            'telephone' => $etablissement?->telephone,
            'email' => $etablissement?->email,
            'coordonnees' => implode(' — ', array_filter([
                $etablissement?->adresse,
                $etablissement?->telephone ? 'Tél : '.$etablissement->telephone : null,
            ])),
            'logo' => self::logo($etablissement),
            'couleur' => $couleur,
            'teinte' => self::teinte($couleur),
            'monnaie' => $etablissement?->monnaie() ?? 'FCFA',
            'republique' => $etablissement?->pays_info['republique'] ?? null,
        ];
    }

    /** Couleur de l'école : couleur principale, sinon couleur des bulletins, sinon la couleur par défaut. */
    public static function couleur(?Etablissement $etablissement): string
    {
        foreach ([$etablissement?->couleur_principale, $etablissement?->bulletin_couleur] as $candidate) {
            if (preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $candidate)) {
                return $candidate;
            }
        }

        return self::COULEUR_DEFAUT;
    }

    /** Version très claire de la couleur (fond des encadrés), sans dépendre du navigateur. */
    public static function teinte(string $couleur, float $opacite = 0.08): string
    {
        [$r, $g, $b] = sscanf($couleur, '#%02x%02x%02x');

        return sprintf('rgba(%d, %d, %d, %.2f)', $r, $g, $b, $opacite);
    }

    /** Logo en data URI ; uniquement un fichier du dossier public, de 1 Mo au plus. */
    public static function logo(?Etablissement $etablissement): ?string
    {
        if (! $etablissement?->logo_path) {
            return null;
        }

        $fichier = realpath(public_path($etablissement->logo_path));
        $racine = (string) realpath(public_path());
        if (! $fichier || ! str_starts_with($fichier, $racine) || ! is_file($fichier) || filesize($fichier) > 1024 * 1024) {
            return null;
        }

        $type = mime_content_type($fichier) ?: 'image/png';
        if (! str_starts_with($type, 'image/')) {
            return null;
        }

        return 'data:'.$type.';base64,'.base64_encode((string) file_get_contents($fichier));
    }
}
