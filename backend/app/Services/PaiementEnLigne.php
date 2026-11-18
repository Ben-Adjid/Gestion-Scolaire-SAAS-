<?php

namespace App\Services;

use App\Models\Etablissement;
use App\Models\FraisScolaire;
use App\Models\Paiement;
use App\Models\TransactionEnLigne;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Paiement en ligne des frais scolaires.
 *
 * - « cinetpay » : mobile money et cartes (Afrique francophone). L'argent arrive sur le compte CinetPay de l'école.
 * - « simulation » : pour essayer le parcours sans compte prestataire ; refusée en production.
 *
 * Sécurité : le statut d'un paiement est toujours redemandé au prestataire (API de vérification) ;
 * le contenu d'une notification n'est jamais cru tel quel. Un paiement réussi crée une seule fois
 * l'écriture « Paiement » (verrou + statut final), donc le reçu et le reste à payer sont à jour.
 */
class PaiementEnLigne
{
    public const PRESTATAIRES = ['cinetpay'];

    private const CINETPAY_API = 'https://api-checkout.cinetpay.com/v2/payment';

    /** Mode simulation autorisé : jamais en production. */
    public static function simulationAutorisee(): bool
    {
        return ! app()->environment('production') && (bool) config('services.paiement_en_ligne.simulation', true);
    }

    /** Prestataire utilisable pour cette école (null si le paiement en ligne n'est pas disponible). */
    public function prestataire(?Etablissement $etablissement): ?string
    {
        if (! $etablissement || ! in_array('paiement_en_ligne', $etablissement->fonctionnalites(), true)) {
            return null;
        }

        $ids = $etablissement->paiement_identifiants ?? [];
        if ($etablissement->paiement_prestataire === 'cinetpay' && filled($ids['api_key'] ?? null) && filled($ids['site_id'] ?? null)) {
            return 'cinetpay';
        }

        return self::simulationAutorisee() ? 'simulation' : null;
    }

    /** Crée la transaction et renvoie l'adresse de la page de paiement du prestataire. */
    public function initier(FraisScolaire $frais, int $montant, User $parent): TransactionEnLigne
    {
        $etablissement = Etablissement::findOrFail($frais->etablissement_id);
        $prestataire = $this->prestataire($etablissement) ?? throw new RuntimeException('Le paiement en ligne n’est pas activé pour cet établissement.');
        $devise = $etablissement->pays_info['code_monnaie'] ?? 'XAF';

        $transaction = TransactionEnLigne::create([
            'etablissement_id' => $etablissement->id,
            'frais_scolaire_id' => $frais->id,
            'etudiant_id' => $frais->etudiant_id,
            'parent_id' => $parent->id,
            'reference' => 'EL'.$etablissement->id.strtoupper(Str::random(14)),
            'montant' => $montant,
            'devise' => $devise,
            'prestataire' => $prestataire,
            'statut' => 'en_attente',
        ]);

        if ($prestataire === 'simulation') {
            $transaction->update(['url_paiement' => null]);

            return $transaction;
        }

        $ids = $etablissement->paiement_identifiants;
        $eleve = $frais->etudiant;
        $reponse = Http::timeout(20)->asJson()->post(self::CINETPAY_API, [
            'apikey' => $ids['api_key'],
            'site_id' => $ids['site_id'],
            'transaction_id' => $transaction->reference,
            'amount' => $montant,
            'currency' => $devise,
            'description' => Str::limit(preg_replace('/[^\pL\pN \-]/u', '', "Frais {$eleve?->prenom} {$eleve?->nom}"), 60, ''),
            'channels' => 'ALL',
            'notify_url' => url('/api/paiement-en-ligne/notification/cinetpay'),
            'return_url' => rtrim((string) config('app.frontend_url', config('app.url')), '/').'/espace-parent?paiement='.$transaction->reference,
            'customer_name' => Str::limit($parent->name, 50, ''),
            'customer_email' => $parent->email,
            'customer_phone_number' => $parent->telephone,
            'metadata' => (string) $transaction->id,
        ]);

        $url = $reponse->json('data.payment_url');
        $transaction->update(['reponse_prestataire' => $reponse->json(), 'url_paiement' => $url]);

        if (! $reponse->successful() || ! $url) {
            $transaction->update(['statut' => 'echoue']);
            Log::warning('CinetPay : initialisation refusée.', ['reference' => $transaction->reference, 'code' => $reponse->json('code'), 'message' => $reponse->json('message')]);
            throw new RuntimeException('Le prestataire de paiement a refusé la demande : '.($reponse->json('description') ?? $reponse->json('message') ?? 'erreur inconnue').'.');
        }

        return $transaction;
    }

    /** Redemande le statut au prestataire et enregistre le paiement s'il est confirmé. */
    public function verifier(TransactionEnLigne $transaction): TransactionEnLigne
    {
        if ($transaction->statut !== 'en_attente' || $transaction->prestataire === 'simulation') {
            return $transaction;
        }

        $etablissement = Etablissement::findOrFail($transaction->etablissement_id);
        $ids = $etablissement->paiement_identifiants ?? [];
        $reponse = Http::timeout(20)->asJson()->post(self::CINETPAY_API.'/check', [
            'apikey' => $ids['api_key'] ?? '',
            'site_id' => $ids['site_id'] ?? '',
            'transaction_id' => $transaction->reference,
        ]);

        $statut = strtoupper((string) $reponse->json('data.status'));
        $montant = (int) $reponse->json('data.amount');

        if ($statut === 'ACCEPTED' && $montant === (int) $transaction->montant) {
            return $this->confirmer($transaction, 'Mobile money / carte ('.($reponse->json('data.payment_method') ?? 'CinetPay').')', $reponse->json());
        }
        if (in_array($statut, ['REFUSED', 'CANCELED', 'CANCELLED'], true)) {
            $transaction->update(['statut' => 'echoue', 'reponse_prestataire' => $reponse->json()]);
        }

        return $transaction->fresh();
    }

    /** Simulation (hors production) : le parent choisit l'issue du paiement. */
    public function simuler(TransactionEnLigne $transaction, bool $reussi): TransactionEnLigne
    {
        if ($transaction->prestataire !== 'simulation' || ! self::simulationAutorisee()) {
            throw new RuntimeException('Simulation indisponible.');
        }
        if ($transaction->statut !== 'en_attente') {
            return $transaction;
        }

        if (! $reussi) {
            $transaction->update(['statut' => 'echoue']);

            return $transaction;
        }

        return $this->confirmer($transaction, 'En ligne (simulation)', ['simulation' => true]);
    }

    /** Enregistre le paiement une seule fois, même si la notification et le retour du parent arrivent ensemble. */
    private function confirmer(TransactionEnLigne $transaction, string $mode, array $reponse): TransactionEnLigne
    {
        return DB::transaction(function () use ($transaction, $mode, $reponse) {
            $t = TransactionEnLigne::withoutGlobalScopes()->whereKey($transaction->id)->lockForUpdate()->firstOrFail();
            if ($t->statut !== 'en_attente') {
                return $t;
            }

            $paiement = Paiement::withoutGlobalScopes()->create([
                'frais_scolaire_id' => $t->frais_scolaire_id,
                'etudiant_id' => $t->etudiant_id,
                'montant' => $t->montant,
                'date_paiement' => now()->toDateString(),
                'mode_paiement' => 'en_ligne',
                'reference' => $t->reference,
                'statut' => 'completed',
                'notes' => $mode,
                'etablissement_id' => $t->etablissement_id,
            ]);

            $t->update(['statut' => 'reussi', 'paiement_id' => $paiement->id, 'reponse_prestataire' => $reponse]);
            AuditLogger::log(null, 'paiement_en_ligne', 'Paiement', $paiement->id,
                "Paiement en ligne de {$t->montant} {$t->devise} (réf. {$t->reference})");

            return $t;
        });
    }
}
