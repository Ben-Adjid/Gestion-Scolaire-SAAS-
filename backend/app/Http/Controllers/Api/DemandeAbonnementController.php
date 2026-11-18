<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DemandeAbonnement;
use App\Models\Etablissement;
use App\Services\AbonnementService;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DemandeAbonnementController extends Controller
{
    public function options(Request $request): JsonResponse
    {
        $mobile = config('paiements.mobile_money');
        $virement = config('paiements.virement');

        return response()->json([
            'devise' => config('paiements.devise'),
            'plan_actuel' => $request->user()->etablissement?->planCode(),
            'formules' => collect(config('plans.plans'))->map(fn ($plan, $code) => [
                'code' => $code,
                'libelle' => $plan['libelle'],
                'prix_mensuel' => $plan['prix_fcfa'],
                'max_utilisateurs' => $plan['max_utilisateurs'],
            ])->values(),
            'methodes' => [
                [
                    'code' => 'mobile_money',
                    'libelle' => $mobile['libelle'],
                    'disponible' => filled($mobile['numero']),
                    'details' => array_filter([
                        'numero' => $mobile['numero'],
                        'titulaire' => $mobile['titulaire'],
                    ], fn ($value) => filled($value)),
                ],
                [
                    'code' => 'virement',
                    'libelle' => 'Virement bancaire',
                    'disponible' => filled($virement['banque']) && filled($virement['compte']),
                    'details' => array_filter($virement, fn ($value) => filled($value)),
                ],
            ],
            'paiement_en_ligne' => [
                'disponible' => false,
                'message' => 'La passerelle sera activée après le choix du prestataire et la configuration de ses identifiants.',
            ],
        ]);
    }

    public function mesDemandes(Request $request): JsonResponse
    {
        $etablissement = $this->etablissement($request);

        return response()->json(
            DemandeAbonnement::where('etablissement_id', $etablissement->id)
                ->latest('id')
                ->limit(10)
                ->get()
                ->map(fn (DemandeAbonnement $demande) => $this->formatDemande($demande))
        );
    }

    public function creer(Request $request): JsonResponse
    {
        $etablissement = $this->etablissement($request);
        $data = $request->validate([
            'plan' => ['required', Rule::in(array_keys(config('plans.plans')))],
            'duree_mois' => 'required|integer|min:1|max:24',
            'mode_paiement' => 'required|in:mobile_money,virement',
            'reference' => 'required|string|max:100',
            'justificatif' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $methode = config('paiements.'.$data['mode_paiement']);
        $methodeDisponible = $data['mode_paiement'] === 'mobile_money'
            ? filled($methode['numero'] ?? null)
            : filled($methode['banque'] ?? null) && filled($methode['compte'] ?? null);
        if (! $methodeDisponible) {
            return response()->json([
                'message' => 'Les coordonnées de paiement ne sont pas encore configurées pour ce mode.',
                'code' => 'mode_paiement_non_configure',
            ], 422);
        }

        if (DemandeAbonnement::where('reference', $data['reference'])->exists()) {
            return response()->json([
                'message' => 'Cette référence de paiement a déjà été utilisée.',
                'code' => 'reference_deja_utilisee',
            ], 409);
        }

        $path = null;
        try {
            if ($request->hasFile('justificatif')) {
                $path = $request->file('justificatif')->store('abonnements/'.$etablissement->id, 'private');
            }

            $demande = DB::transaction(function () use ($request, $etablissement, $data, $path) {
                Etablissement::whereKey($etablissement->id)->lockForUpdate()->firstOrFail();
                if (DemandeAbonnement::where('etablissement_id', $etablissement->id)->where('statut', 'en_attente')->exists()) {
                    abort(409, 'Une demande de renouvellement est déjà en attente de validation.');
                }

                return DemandeAbonnement::create([
                    'etablissement_id' => $etablissement->id,
                    'user_id' => $request->user()->id,
                    'plan' => $data['plan'],
                    'duree_mois' => $data['duree_mois'],
                    'montant' => config('plans.plans.'.$data['plan'].'.prix_fcfa') * $data['duree_mois'],
                    'mode_paiement' => $data['mode_paiement'],
                    'reference' => $data['reference'],
                    'justificatif_path' => $path,
                    'statut' => 'en_attente',
                ]);
            });
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('private')->delete($path);
            }
            throw $e;
        }

        AuditLogger::log($request->user(), 'demande_renouvellement_abonnement', 'DemandeAbonnement', $demande->id,
            "Demande {$demande->plan} ({$demande->montant} ".config('paiements.devise').") soumise.");

        return response()->json([
            'message' => 'Demande envoyée. L’accès sera réactivé après vérification du paiement.',
            'demande' => $this->formatDemande($demande),
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $demandes = DemandeAbonnement::with(['etablissement:id,nom', 'demandeur:id,name,email'])
            ->when($request->query('statut'), fn ($query, $statut) => $query->where('statut', $statut))
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(fn (DemandeAbonnement $demande) => $this->formatDemande($demande, true));

        return response()->json($demandes);
    }

    public function justificatif(DemandeAbonnement $demande): StreamedResponse
    {
        abort_unless($demande->justificatif_path, 404);
        abort_unless(Storage::disk('private')->exists($demande->justificatif_path), 404);

        return Storage::disk('private')->download(
            $demande->justificatif_path,
            basename($demande->justificatif_path)
        );
    }

    public function approuver(Request $request, DemandeAbonnement $demande, AbonnementService $abonnements): JsonResponse
    {
        $paiement = DB::transaction(function () use ($request, $demande, $abonnements) {
            $verrouillee = DemandeAbonnement::whereKey($demande->id)->lockForUpdate()->firstOrFail();
            abort_unless($verrouillee->statut === 'en_attente', 409, 'Cette demande a déjà été traitée.');

            $paiement = $abonnements->renouveler(
                Etablissement::findOrFail($verrouillee->etablissement_id),
                $verrouillee->plan,
                $verrouillee->duree_mois,
                $verrouillee->montant,
                $verrouillee->mode_paiement,
                $verrouillee->reference,
                $request->user(),
            );

            $verrouillee->update([
                'statut' => 'approuvee',
                'traitee_par' => $request->user()->id,
                'abonnement_paiement_id' => $paiement->id,
            ]);

            return $paiement;
        });

        return response()->json(['message' => 'Paiement vérifié et abonnement activé.', 'paiement' => $paiement]);
    }

    public function refuser(Request $request, DemandeAbonnement $demande): JsonResponse
    {
        $data = $request->validate(['motif_refus' => 'required|string|max:1000']);

        DB::transaction(function () use ($request, $demande, $data) {
            $verrouillee = DemandeAbonnement::whereKey($demande->id)->lockForUpdate()->firstOrFail();
            abort_unless($verrouillee->statut === 'en_attente', 409, 'Cette demande a déjà été traitée.');

            $verrouillee->update([
                'statut' => 'refusee',
                'traitee_par' => $request->user()->id,
                'motif_refus' => $data['motif_refus'],
            ]);
        });

        return response()->json(['message' => 'Demande refusée.']);
    }

    private function etablissement(Request $request): Etablissement
    {
        $etablissement = $request->user()->etablissement;
        abort_unless($etablissement, 403, 'Aucun établissement n’est associé à ce compte.');

        return $etablissement;
    }

    private function formatDemande(DemandeAbonnement $demande, bool $avecEtablissement = false): array
    {
        $resultat = [
            'id' => $demande->id,
            'plan' => $demande->plan,
            'duree_mois' => $demande->duree_mois,
            'montant' => $demande->montant,
            'mode_paiement' => $demande->mode_paiement,
            'reference' => $demande->reference,
            'statut' => $demande->statut,
            'motif_refus' => $demande->motif_refus,
            'a_justificatif' => (bool) $demande->justificatif_path,
            'justificatif_extension' => $demande->justificatif_path ? pathinfo($demande->justificatif_path, PATHINFO_EXTENSION) : null,
            'created_at' => $demande->created_at,
        ];

        if ($avecEtablissement) {
            $resultat['etablissement'] = $demande->etablissement?->only(['id', 'nom']);
            $resultat['demandeur'] = $demande->demandeur?->only(['id', 'name', 'email']);
        }

        return $resultat;
    }
}
