<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DemandeAbonnement;
use App\Models\Etablissement;
use App\Models\EvenementPaiement;
use App\Services\AbonnementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Reçoit les résultats de paiement du prestataire.
 * - Authentifié par signature HMAC-SHA256 du corps brut (en-tête X-Signature).
 * - Idempotent : une référence de paiement n'est traitée qu'une fois.
 * - Seules les demandes de renouvellement en attente, au montant enregistré, peuvent être réglées.
 * - Un échec de paiement ne suspend jamais un abonnement déjà actif.
 */
class PaiementWebhookController extends Controller
{
    public function recevoir(Request $request, AbonnementService $abonnements): JsonResponse
    {
        $secret = config('services.paiement.secret');
        if (! $secret) {
            return response()->json(['message' => 'Webhook de paiement non configuré.'], 503);
        }

        $attendue = hash_hmac('sha256', $request->getContent(), $secret);
        if (! hash_equals($attendue, (string) $request->header('X-Signature', ''))) {
            return response()->json(['message' => 'Signature invalide.'], 401);
        }

        $data = $request->validate([
            'reference' => 'required|string|max:100',
            'etablissement_id' => 'required|integer|exists:etablissements,id',
            'statut' => 'required|in:reussi,echoue',
            'montant' => 'nullable|integer|min:0',
        ]);

        $demande = DemandeAbonnement::where('reference', $data['reference'])
            ->where('etablissement_id', $data['etablissement_id'])
            ->first();
        if (! $demande) {
            return response()->json(['message' => 'Aucune demande de paiement correspond à cette référence.'], 404);
        }

        $resultat = DB::transaction(function () use ($data, $demande, $abonnements) {
            $demande = DemandeAbonnement::whereKey($demande->id)->lockForUpdate()->firstOrFail();
            if (EvenementPaiement::where('reference', $data['reference'])->exists()) {
                return 'ignore';
            }
            if ($demande->statut !== 'en_attente') {
                return 'ignore';
            }
            if (isset($data['montant']) && (int) $data['montant'] !== $demande->montant) {
                return 'montant_invalide';
            }

            EvenementPaiement::create([
                'reference' => $data['reference'],
                'etablissement_id' => $demande->etablissement_id,
                'statut' => $data['statut'],
                'charge' => $data,
            ]);

            if ($data['statut'] === 'echoue') {
                $demande->update([
                    'statut' => 'refusee',
                    'motif_refus' => 'Le prestataire a signalé un paiement échoué.',
                ]);

                return 'echoue';
            }

            $paiement = $abonnements->renouveler(
                Etablissement::findOrFail($demande->etablissement_id),
                $demande->plan,
                $demande->duree_mois,
                $demande->montant,
                $demande->mode_paiement,
                $demande->reference,
            );
            $demande->update([
                'statut' => 'approuvee',
                'abonnement_paiement_id' => $paiement->id,
            ]);

            return 'reussi';
        });

        return match ($resultat) {
            'ignore' => response()->json(['message' => 'Événement déjà traité.', 'statut' => 'ignore']),
            'montant_invalide' => response()->json(['message' => 'Le montant reçu ne correspond pas à la demande.'], 422),
            'echoue' => response()->json(['message' => 'Paiement échoué. L’abonnement actuel n’a pas été modifié.', 'statut' => 'echoue']),
            default => response()->json(['message' => 'Paiement vérifié et abonnement activé.', 'statut' => 'active']),
        };
    }
}
