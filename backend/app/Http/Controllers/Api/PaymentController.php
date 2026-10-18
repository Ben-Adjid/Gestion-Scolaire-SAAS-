<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FraisScolaire;
use App\Models\Paiement;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin,gestionnaire')->only(['recordPayment']);
    }

    /**
     * Lister les frais scolaires d'une famille
     */
    public function frais(Request $request, $etudiantId): JsonResponse
    {
        $frais = FraisScolaire::where('etudiant_id', $etudiantId)
            ->with('paiements')
            ->get();

        $summary = [
            'total_due' => $frais->sum('montant'),
            'total_paid' => $frais->sum(function ($f) {
                return $f->paiements->sum('montant');
            }),
            'overdue' => $frais->filter(fn ($f) => 
                $f->date_echeance < today() && 
                $f->paiements->sum('montant') < $f->montant
            )->count(),
        ];

        return response()->json([
            'frais' => $frais,
            'summary' => $summary,
        ]);
    }

    /**
     * Enregistrer un paiement
     */
    public function recordPayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'frais_scolaire_id' => 'required|exists:frais_scolaires,id',
            'montant' => 'required|numeric|min:0.01',
            'date_paiement' => 'required|date',
            'mode_paiement' => 'required|in:virement,cheque,espece,carte',
            'reference' => 'nullable|string|max:100',
        ]);

        $paiement = Paiement::create($validated + [
            'date_paiement' => $validated['date_paiement'],
        ]);

        AuditLogger::log($request->user(), 'création', 'paiements', $paiement->id,
            "Enregistrement d'un paiement de {$validated['montant']}€");

        return response()->json([
            'message' => 'Paiement enregistré avec succès.',
            'paiement' => $paiement->load('fraisScolaire'),
            'recu_url' => '/api/paiements/' . $paiement->id . '/recu',
        ], 201);
    }

    /**
     * Stripe webhook pour paiements en ligne
     */
    public function stripeWebhook(Request $request): JsonResponse
    {
        $event = json_decode($request->getContent(), true);

        try {
            match ($event['type']) {
                'payment_intent.succeeded' => $this->handlePaymentSuccess($event['data']['object']),
                'payment_intent.payment_failed' => $this->handlePaymentFailed($event['data']['object']),
            };

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('Stripe webhook error', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Webhook failed'], 500);
        }
    }

    private function handlePaymentSuccess($paymentIntent)
    {
        // Enregistrer le paiement en base de données
        // Envoyer la confirmation à la famille
        // Notifier le trésorier
        Log::info('Payment succeeded', ['amount' => $paymentIntent['amount'] / 100]);
    }

    private function handlePaymentFailed($paymentIntent)
    {
        Log::warning('Payment failed', ['amount' => $paymentIntent['amount'] / 100]);
        // Envoyer alerte à la famille
    }
}
