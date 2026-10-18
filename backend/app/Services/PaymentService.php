<?php

namespace App\Services;

use App\Models\Paiement;
use App\Models\FraisScolaire;
use Stripe\StripeClient;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    private $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(config('services.stripe.secret'));
    }

    public function recordPayment(FraisScolaire $frais, array $data)
    {
        $paiement = Paiement::create([
            'frais_scolaire_id' => $frais->id,
            'etudiant_id' => $frais->etudiant_id,
            'montant' => $data['montant'],
            'date_paiement' => $data['date_paiement'] ?? today(),
            'mode_paiement' => $data['mode_paiement'],
            'reference' => $data['reference'] ?? null,
            'statut' => 'completed',
            'etablissement_id' => $frais->etablissement_id,
        ]);

        $this->updateFraisStatus($frais);

        return $paiement;
    }

    public function updateFraisStatus(FraisScolaire $frais)
    {
        $totalPaid = $frais->totalPaid();
        
        if ($totalPaid >= $frais->montant) {
            $frais->update(['statut' => 'paid']);
        } elseif ($totalPaid > 0) {
            $frais->update(['statut' => 'partial']);
        } else {
            $frais->update(['statut' => $frais->date_echeance < today() ? 'overdue' : 'pending']);
        }
    }

    public function handleStripeWebhook($payload)
    {
        $event = $payload['type'];
        $data = $payload['data']['object'];

        if ($event === 'payment_intent.succeeded') {
            $this->handleSuccessfulPayment($data);
        } elseif ($event === 'payment_intent.payment_failed') {
            $this->handleFailedPayment($data);
        }

        Log::info("Stripe webhook processed: {$event}");
    }

    private function handleSuccessfulPayment($data)
    {
        $reference = $data['metadata']['frais_id'] ?? null;
        if (!$reference) return;

        $frais = FraisScolaire::find($reference);
        if (!$frais) return;

        $this->recordPayment($frais, [
            'montant' => $data['amount'] / 100,
            'mode_paiement' => 'carte',
            'reference' => $data['id'],
        ]);
    }

    private function handleFailedPayment($data)
    {
        Log::warning("Payment failed for Stripe ID: {$data['id']}", $data);
    }
}
