<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Etablissement;
use App\Models\FraisScolaire;
use App\Models\TransactionEnLigne;
use App\Services\AuditLogger;
use App\Services\PaiementEnLigne;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaiementEnLigneController extends Controller
{
    public function __construct(private PaiementEnLigne $service)
    {
        $this->middleware('role:parent')->only(['payer', 'suivre', 'simuler']);
        $this->middleware('role:admin')->only(['configuration', 'configurer']);
    }

    // ---------------------------------------------------------------- Parent

    public function payer(Request $request, FraisScolaire $frais): JsonResponse
    {
        $parent = $request->user();
        abort_unless(
            $parent->enfants()->where('etudiants.id', $frais->etudiant_id)->where('etudiants.etablissement_id', $parent->etablissement_id)->exists(),
            403,
            'Ces frais ne concernent pas un de vos enfants.'
        );

        $reste = (int) floor($frais->reste);
        $data = $request->validate([
            'montant' => ['required', 'integer', 'min:100', 'max:'.max(100, $reste)],
        ], ['montant.max' => "Le montant ne peut pas dépasser le reste à payer ({$reste})."]);
        abort_if($reste <= 0, 422, 'Ces frais sont déjà soldés.');

        // CinetPay exige un multiple de 5 pour le franc CFA.
        if ((int) $data['montant'] % 5 !== 0 && in_array($parent->etablissement?->pays_info['code_monnaie'] ?? '', ['XOF', 'XAF'], true)) {
            return response()->json(['message' => 'Le montant doit être un multiple de 5.', 'errors' => ['montant' => ['Le montant doit être un multiple de 5.']]], 422);
        }

        try {
            $transaction = $this->service->initier($frais, (int) $data['montant'], $parent);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'reference' => $transaction->reference,
            'prestataire' => $transaction->prestataire,
            'url_paiement' => $transaction->url_paiement,
            'montant' => $transaction->montant,
            'devise' => $transaction->devise,
        ], 201);
    }

    /** Retour du parent après la page de paiement : statut à jour (redemandé au prestataire). */
    public function suivre(Request $request, string $reference): JsonResponse
    {
        $transaction = TransactionEnLigne::where('reference', $reference)->where('parent_id', $request->user()->id)->firstOrFail();
        $transaction = $this->service->verifier($transaction);

        return response()->json($transaction->only(['reference', 'statut', 'montant', 'devise', 'prestataire', 'paiement_id']));
    }

    public function simuler(Request $request, string $reference): JsonResponse
    {
        $data = $request->validate(['issue' => ['required', Rule::in(['reussi', 'echoue'])]]);
        $transaction = TransactionEnLigne::where('reference', $reference)->where('parent_id', $request->user()->id)->firstOrFail();

        try {
            $transaction = $this->service->simuler($transaction, $data['issue'] === 'reussi');
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        return response()->json($transaction->only(['reference', 'statut', 'montant', 'devise', 'paiement_id']));
    }

    // ---------------------------------------------------------------- Prestataire (sans session)

    /**
     * Notification du prestataire. Le corps sert seulement à retrouver la transaction :
     * le statut est redemandé à l'API du prestataire avant toute écriture.
     */
    public function notification(Request $request, string $prestataire): JsonResponse
    {
        abort_unless($prestataire === 'cinetpay', 404);
        $reference = (string) $request->input('cpm_trans_id', $request->input('transaction_id', ''));

        $transaction = TransactionEnLigne::withoutGlobalScopes()->where('reference', $reference)->where('prestataire', 'cinetpay')->first();
        if ($transaction) {
            $this->service->verifier($transaction);
        }

        // Réponse identique dans tous les cas : rien n'est révélé sur l'existence d'une référence.
        return response()->json(['message' => 'Reçu.']);
    }

    // ---------------------------------------------------------------- Administration de l'école

    public function configuration(Request $request): JsonResponse
    {
        $etab = Etablissement::findOrFail($request->user()->etablissement_id);
        $ids = $etab->paiement_identifiants ?? [];

        return response()->json([
            'prestataire' => $etab->paiement_prestataire,
            'site_id' => $ids['site_id'] ?? null,
            // La clé n'est jamais renvoyée : seulement ses 4 derniers caractères.
            'api_key_masquee' => filled($ids['api_key'] ?? null) ? '••••'.substr($ids['api_key'], -4) : null,
            'actif' => $this->service->prestataire($etab),
            'simulation_autorisee' => PaiementEnLigne::simulationAutorisee(),
            'url_notification' => url('/api/paiement-en-ligne/notification/cinetpay'),
        ]);
    }

    public function configurer(Request $request): JsonResponse
    {
        $data = $request->validate([
            'prestataire' => ['nullable', Rule::in(PaiementEnLigne::PRESTATAIRES)],
            'site_id' => ['nullable', 'required_with:prestataire', 'string', 'max:50'],
            'api_key' => ['nullable', 'string', 'max:200'],
        ]);

        $etab = Etablissement::findOrFail($request->user()->etablissement_id);
        $ids = $etab->paiement_identifiants ?? [];

        if (empty($data['prestataire'])) {
            $etab->forceFill(['paiement_prestataire' => null, 'paiement_identifiants' => null])->save();
        } else {
            // Clé vide = garder la clé déjà enregistrée.
            $apiKey = filled($data['api_key'] ?? null) ? $data['api_key'] : ($ids['api_key'] ?? null);
            if (blank($apiKey)) {
                return response()->json(['message' => 'La clé API est obligatoire.', 'errors' => ['api_key' => ['La clé API est obligatoire.']]], 422);
            }
            $etab->forceFill([
                'paiement_prestataire' => $data['prestataire'],
                'paiement_identifiants' => ['api_key' => $apiKey, 'site_id' => $data['site_id']],
            ])->save();
        }

        AuditLogger::log($request->user(), 'configuration_paiement_en_ligne', 'etablissements', $etab->id,
            'Paiement en ligne : '.($data['prestataire'] ?? 'désactivé'));

        return $this->configuration($request);
    }
}
