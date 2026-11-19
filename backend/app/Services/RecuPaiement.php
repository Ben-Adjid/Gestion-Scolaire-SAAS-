<?php

namespace App\Services;

use App\Models\Etablissement;
use App\Models\Paiement;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

/**
 * Reçu de paiement officiel côté serveur : mêmes données et même mise en page que le reçu de l'écran,
 * utilisé pour la pièce jointe des e-mails envoyés au payeur ou au tuteur.
 */
class RecuPaiement
{
    private const MODES = [
        'especes' => 'Espèces',
        'cheque' => 'Chèque',
        'virement' => 'Virement',
        'mobile_money' => 'Mobile money',
        'carte' => 'Carte bancaire',
        'en_ligne' => 'Paiement en ligne',
    ];

    /** Données du reçu, dans la même forme que construireRecu() côté écran. */
    public static function donnees(Paiement $paiement): array
    {
        $paiement->loadMissing(['etudiant.classe:id,nom,niveau', 'fraisScolaire', 'agent:id,name']);
        $frais = $paiement->fraisScolaire;
        $etudiant = $paiement->etudiant;
        $etablissement = Etablissement::find($paiement->etablissement_id);
        $identite = IdentiteEtablissement::pour($etablissement);
        $monnaie = $identite['monnaie'];

        $annee = $frais?->annee_scolaire ?? $etablissement?->annee_scolaire ?? '';
        $anneeRef = preg_match('/^(\d{4})/', (string) $annee, $m) ? $m[1] : date('Y');
        $reste = max(0.0, (float) ($frais?->reste ?? 0));
        $classe = $etudiant?->classe;

        return [
            'numero' => 'REC-'.$anneeRef.'-'.str_pad((string) $paiement->id, 4, '0', STR_PAD_LEFT),
            'dateHeure' => Carbon::parse($paiement->created_at ?? now())->locale('fr')->isoFormat('D MMMM YYYY [à] HH:mm'),
            'ecole' => [
                'nom' => $identite['nom'],
                'adresse' => $identite['adresse'] ?? '',
                'ville' => $identite['ville'] ?? '',
                'pays' => $identite['pays'] ?? '',
                'telephone' => $identite['telephone'] ?? '',
                'email' => $identite['email'] ?? '',
                'anneeScolaire' => $etablissement?->annee_scolaire ?? $annee,
                'couleur' => $identite['couleur'],
                'teinte' => $identite['teinte'],
                'logo' => $identite['logo'] ?? self::logoPlateforme(),
            ],
            'eleve' => [
                'nom' => trim(($etudiant?->prenom ?? '').' '.($etudiant?->nom ?? '')) ?: '—',
                'matricule' => $etudiant?->matricule ?: '—',
                'classe' => trim(implode(' • ', array_filter([$classe?->nom, $classe?->niveau ? self::libelleNiveau($classe->niveau) : null]))) ?: '—',
            ],
            'frais' => [
                'libelle' => trim(($frais?->libelle ?? 'Frais de scolarité').($annee ? " ({$annee})" : '')),
                'exigible' => self::somme($frais?->montant_total ?? 0, $monnaie),
                'totalPaye' => self::somme($frais?->montant_paye ?? 0, $monnaie),
                'reste' => self::somme($reste, $monnaie),
                'solde' => $reste <= 0,
            ],
            'montantEncaisse' => self::somme($paiement->montant, $monnaie),
            'mode' => self::MODES[$paiement->mode_paiement] ?? ucfirst((string) $paiement->mode_paiement),
            'reference' => $paiement->reference ?? '',
            'payeur' => $paiement->payeur_affiche ?? '—',
            'agent' => $paiement->agent?->name ?? '',
        ];
    }

    /** Reçu au format PDF (A4), prêt à joindre à un e-mail. */
    public static function generer(Paiement $paiement): string
    {
        return Pdf::loadView('documents.recu-paiement-pdf', ['r' => self::donnees($paiement)])
            ->setPaper('a4', 'portrait')
            ->output();
    }

    /** Montant lisible : espaces de milliers et monnaie de l'école (« 50 000 FCFA »). */
    private static function somme(mixed $valeur, string $monnaie): string
    {
        return number_format((float) $valeur, 0, ',', ' ').' '.$monnaie;
    }

    /** Libellé de niveau comme à l'écran : Collège ou Lycée, sinon le niveau tel quel. */
    private static function libelleNiveau(string $niveau): string
    {
        $n = mb_strtolower(trim($niveau));
        if (preg_match('/^(6|5|4|3)/', $n) || str_contains($n, 'coll') || str_contains($n, 'clg')) {
            return 'Collège';
        }
        if (preg_match('/^(2|1|t)/', $n) || str_contains($n, 'seconde') || str_contains($n, 'première')
            || str_contains($n, 'premiere') || str_contains($n, 'terminale') || str_contains($n, 'lyc')) {
            return 'Lycée';
        }

        return mb_strtoupper(mb_substr($niveau, 0, 1)).mb_substr($niveau, 1);
    }

    /** Logo de la plateforme (icône PWA), intégré au document quand l'école n'en a pas. */
    private static function logoPlateforme(): ?string
    {
        $fichier = resource_path('images/logo-plateforme.png');

        return is_file($fichier) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($fichier)) : null;
    }
}
