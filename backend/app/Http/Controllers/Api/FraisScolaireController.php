<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Classe;
use App\Models\Etablissement;
use App\Models\Etudiant;
use App\Models\FraisScolaire;
use App\Models\Paiement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class FraisScolaireController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('role:admin,proviseur,gestionnaire,agent_comptable')->only(['index', 'stats', 'show', 'paiements', 'export', 'recu']);
        $this->middleware('role:admin,gestionnaire,agent_comptable')->only(['store', 'update', 'bulkInit', 'addPaiement', 'destroy']);
    }

    public function index(Request $request): JsonResponse
    {
        $query = FraisScolaire::query()->with(['etudiant:id,nom,prenom,matricule,classe_id', 'etudiant.classe:id,nom,niveau']);

        if ($request->etudiant_id) {
            $query->where('etudiant_id', $request->etudiant_id);
        }

        if ($request->annee_scolaire) {
            $query->where('annee_scolaire', $request->annee_scolaire);
        }

        if ($request->statut) {
            $query->where('statut', $request->statut);
        }

        return response()->json($query->orderByDesc('id')->paginate(20));
    }

    public function stats(Request $request): JsonResponse
    {
        // Les totaux suivent l'année affichée à l'écran, comme la liste.
        $frais = FraisScolaire::query()
            ->when($request->query('annee_scolaire'), fn ($q, $annee) => $q->where('annee_scolaire', $annee));

        $montantTotal = (float) (clone $frais)->sum('montant');
        $montantPaye = (float) Paiement::where('statut', 'completed')
            ->whereIn('frais_scolaire_id', (clone $frais)->select('id'))
            ->sum('montant');

        return response()->json([
            'montant_total' => $montantTotal,
            'montant_paye' => $montantPaye,
            'reste' => $montantTotal - $montantPaye,
            'effectif' => (clone $frais)->distinct('etudiant_id')->count('etudiant_id'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'etudiant_id' => 'required|integer|exists:etudiants,id',
            'annee_scolaire' => 'required|string|max:20',
            'montant_total' => 'required|numeric|min:0',
            'libelle' => 'nullable|string|max:255',
            'date_echeance' => 'nullable|date',
        ]);

        $frais = FraisScolaire::create($this->donneesFrais($validated));

        AuditLogger::log($request->user(), 'create', 'FraisScolaire', $frais->id, "Nouveaux frais créés pour l'élève {$frais->etudiant_id}");

        return response()->json(['frais' => $frais->load('etudiant:id,nom,prenom,matricule')], 201);
    }

    public function show(FraisScolaire $frais): JsonResponse
    {
        return response()->json($frais->load([
            'etudiant:id,nom,prenom,matricule,classe_id',
            'etudiant.classe:id,nom,niveau',
            'paiements.agent:id,name',
        ]));
    }

    public function update(Request $request, FraisScolaire $frais): JsonResponse
    {
        $validated = $request->validate([
            'libelle' => 'nullable|string|max:255',
            'montant_total' => 'nullable|numeric|min:0',
            'date_echeance' => 'nullable|date',
            'statut' => 'nullable|in:pending,partial,paid,overdue',
        ]);

        $valeurs = fn (FraisScolaire $f) => [
            'libelle' => $f->libelle,
            'montant' => (float) $f->montant,
            'date_echeance' => (string) $f->date_echeance,
            'statut' => $f->statut,
        ];
        // Le montant total ne peut pas passer sous ce qui est déjà encaissé.
        if (isset($validated['montant_total']) && (float) $validated['montant_total'] < $frais->montant_paye) {
            return response()->json([
                'message' => 'Le montant total ne peut pas être inférieur au montant déjà encaissé ('.number_format($frais->montant_paye, 0, ',', ' ').').',
                'errors' => ['montant_total' => ['Le montant total ne peut pas être inférieur au montant déjà encaissé.']],
            ], 422);
        }

        $avant = $valeurs($frais);
        $frais->update($this->donneesFrais($validated, $frais));

        AuditLogger::log($request->user(), 'update', 'FraisScolaire', $frais->id, "Mise à jour des frais",
            $avant, $valeurs($frais->fresh()));

        return response()->json(['frais' => $frais->fresh()->load('etudiant:id,nom,prenom,matricule')]);
    }

    public function bulkInit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'classe_id' => 'required|integer|exists:classes,id',
            'montant_total' => 'required|numeric|min:0',
            'annee_scolaire' => 'nullable|string|max:20',
            'libelle' => 'nullable|string|max:255',
            'date_echeance' => 'nullable|date',
        ]);

        $etudiants = Etudiant::where('classe_id', $validated['classe_id'])->get();

        if ($etudiants->isEmpty()) {
            return response()->json(['message' => 'Cette classe ne contient aucun élève.'], 422);
        }

        $annee = $validated['annee_scolaire'] ?? Etablissement::value('annee_scolaire');

        $crees = DB::transaction(fn () => $etudiants->map(fn (Etudiant $etudiant) => FraisScolaire::create($this->donneesFrais([
            'etudiant_id' => $etudiant->id,
            'annee_scolaire' => $annee,
            'montant_total' => $validated['montant_total'],
            'libelle' => $validated['libelle'] ?? null,
            'date_echeance' => $validated['date_echeance'] ?? null,
        ]))));

        AuditLogger::log($request->user(), 'create', 'FraisScolaire', null,
            "Frais créés en masse pour {$crees->count()} élève(s) de la classe {$validated['classe_id']}");

        return response()->json(['message' => "Frais créés pour {$crees->count()} élève(s).", 'count' => $crees->count()], 201);
    }

    public function paiements(FraisScolaire $frais): JsonResponse
    {
        return response()->json($frais->paiements()->with('agent:id,name')->orderByDesc('date_paiement')->get());
    }

    /** Suppression : impossible dès qu'un paiement a été encaissé (on garde la trace comptable). */
    public function destroy(Request $request, FraisScolaire $frais): JsonResponse
    {
        if ($frais->paiements()->exists()) {
            return response()->json([
                'message' => 'Ces frais ont déjà des paiements encaissés : ils ne peuvent pas être supprimés. Corrigez le montant si besoin.',
            ], 422);
        }

        $libelle = $frais->libelle;
        $frais->delete();
        AuditLogger::log($request->user(), 'suppression', 'FraisScolaire', $frais->id, "Suppression des frais « {$libelle} »");

        return response()->json(['message' => 'Frais supprimés.']);
    }

    /**
     * Envoie le reçu PDF aux parents rattachés à l'élève qui ont une adresse e-mail.
     * Un échec d'envoi ne bloque jamais l'encaissement : il est seulement journalisé.
     */
    private function envoyerRecu(Paiement $paiement): void
    {
        // Seuls les tuteurs qui ont une adresse e-mail et n'ont pas désactivé l'envoi.
        $destinataires = $paiement->etudiant?->parents()
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->where('recevoir_recu_email', true)
            ->pluck('email')
            ->unique()
            ->values()
            ->all();

        if ($destinataires === []) {
            return;
        }

        try {
            // Mise en file d'attente : l'envoi (et le PDF) se fait hors de la requête, par le worker.
            \Illuminate\Support\Facades\Mail::to($destinataires)->queue(new \App\Mail\PaymentReceiptMail($paiement));
        } catch (\Throwable $e) {
            // Un problème de file ou de messagerie ne doit jamais annuler un encaissement déjà enregistré.
            \Illuminate\Support\Facades\Log::error('Reçu non mis en file d’attente : l’encaissement reste valide.', [
                'paiement_id' => $paiement->id,
                'erreur' => $e->getMessage(),
            ]);
        }
    }

    public function addPaiement(Request $request, FraisScolaire $frais): JsonResponse
    {
        $validated = $request->validate([
            'montant' => 'required|numeric|min:0.01',
            'payeur_nom' => 'nullable|string|max:120',
            'date_paiement' => 'required|date',
            // Paiements au guichet uniquement : espèces ou chèque.
            'mode' => 'required|in:especes,cheque',
            'reference' => 'nullable|string|max:100',
        ], ['mode.in' => 'Le mode de paiement doit être espèces ou chèque.']);

        $frais->loadMissing('paiements');
        if ($frais->reste <= 0) {
            return response()->json(['message' => 'Ces frais sont déjà soldés : aucun paiement supplémentaire n’est possible.'], 422);
        }
        if ($validated['montant'] > $frais->reste) {
            return response()->json([
                'message' => "Le montant dépasse le reste à payer ({$frais->reste} ".($request->user()->etablissement?->monnaie() ?? 'FCFA').").",
            ], 422);
        }

        $paiement = Paiement::create([
            'frais_scolaire_id' => $frais->id,
            'etudiant_id' => $frais->etudiant_id,
            'montant' => $validated['montant'],
            'date_paiement' => $validated['date_paiement'],
            'mode_paiement' => $validated['mode'],
            'enregistre_par' => $request->user()->id,
            'payeur_nom' => filled($validated['payeur_nom'] ?? null) ? trim($validated['payeur_nom']) : null,
            'reference' => $validated['reference'] ?? null,
            'statut' => 'completed',
        ]);

        AuditLogger::log($request->user(), 'create', 'Paiement', $paiement->id, "Paiement de {$paiement->montant} FCFA pour les frais {$frais->id}",
            null, [
                'frais_id' => $frais->id,
                'etudiant_id' => $paiement->etudiant_id,
                'montant' => (float) $paiement->montant,
                'mode' => $paiement->mode_paiement,
                'date' => (string) $paiement->date_paiement,
                'reference' => $paiement->reference,
            ]);

        $this->envoyerRecu($paiement);

        return response()->json([
            'paiement' => $paiement,
            'frais' => $frais->fresh()->load('etudiant:id,nom,prenom,matricule'),
        ], 201);
    }

    public function export(Request $request)
    {
        $frais = FraisScolaire::with('etudiant:id,nom,prenom,matricule')
            ->when($request->query('annee_scolaire'), fn ($q, $v) => $q->where('annee_scolaire', $v))
            ->orderBy('id')
            ->get();

        $entetes = ['Matricule', 'Nom', 'Prénom', 'Année scolaire', 'Montant total', 'Payé', 'Reste', 'Statut'];
        $lignes = [];
        foreach ($frais as $f) {
            $lignes[] = [
                $f->etudiant?->matricule, $f->etudiant?->nom, $f->etudiant?->prenom, $f->annee_scolaire,
                $f->montant_total, $f->montant_paye, $f->reste,
                $f->reste <= 0 ? 'Soldé' : ($f->montant_paye > 0 ? 'Partiel' : 'Impayé'),
            ];
        }

        // Excel mis en forme par défaut ; CSV sur demande (format=csv).
        if ($request->query('format') === 'csv') {
            $csv = fopen('php://temp', 'r+');
            fwrite($csv, "\xEF\xBB\xBF");
            fputcsv($csv, $entetes, ';');
            foreach ($lignes as $ligne) {
                fputcsv($csv, $ligne, ';');
            }
            rewind($csv);
            $contenu = stream_get_contents($csv);
            fclose($csv);

            return response($contenu, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="frais-scolaires.csv"',
            ]);
        }

        $contenu = \App\Services\Xlsx::generer('Frais scolaires', $entetes, $lignes, ['Montant total', 'Payé', 'Reste'],
            implode(' — ', array_filter([$request->user()->etablissement?->nom, 'Frais scolaires'])));

        return response($contenu, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="frais-scolaires.xlsx"',
        ]);
    }

    public function recu(Paiement $paiement)
    {
        $paiement->load(['etudiant:id,nom,prenom,matricule', 'fraisScolaire']);
        $frais = $paiement->fraisScolaire;
        $etablissement = Etablissement::find($paiement->etablissement_id);
        $identite = \App\Services\IdentiteEtablissement::pour($etablissement);
        $montant = fn ($v) => number_format((float) $v, 0, ',', ' ');
        $modes = ['especes' => 'Espèces', 'virement' => 'Virement', 'cheque' => 'Chèque', 'mobile_money' => 'Mobile money', 'carte' => 'Carte bancaire', 'en_ligne' => 'Paiement en ligne'];

        // Gabarit Blade : échappement automatique, mise en page aux couleurs de l'école.
        $html = view('documents.recu-paiement', [
            'numero' => 'REC-'.str_pad((string) $paiement->id, 6, '0', STR_PAD_LEFT),
            'ecole' => $identite,
            'logo' => $identite['logo'],
            'couleur' => $identite['couleur'],
            'teinte' => $identite['teinte'],
            'datePaiement' => $paiement->date_paiement?->format('d/m/Y'),
            'eleve' => [
                'nom' => trim(($paiement->etudiant?->prenom ?? '').' '.($paiement->etudiant?->nom ?? '')),
                'matricule' => $paiement->etudiant?->matricule,
            ],
            'frais' => [
                'libelle' => $frais?->libelle ?? 'Frais de scolarité',
                'annee' => $frais?->annee_scolaire,
                'total' => $montant($frais?->montant_total),
                'paye' => $montant($frais?->montant_paye),
                'reste' => $montant($frais?->reste),
            ],
            'solde' => $frais && (float) $frais->reste <= 0,
            'mode' => $modes[$paiement->mode_paiement] ?? ucfirst((string) $paiement->mode_paiement),
            'reference' => $paiement->reference,
            'montant' => $montant($paiement->montant),
            'emisLe' => now()->format('d/m/Y à H:i'),
        ])->render();

        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    private function donneesFrais(array $validated, ?FraisScolaire $existant = null): array
    {
        $donnees = collect($validated)->only(['etudiant_id', 'annee_scolaire', 'libelle', 'date_echeance', 'statut'])->all();

        if (array_key_exists('montant_total', $validated) && $validated['montant_total'] !== null) {
            $donnees['montant'] = $validated['montant_total'];
        }

        $donnees['libelle'] = $donnees['libelle'] ?? $existant?->libelle
            ?? 'Frais de scolarité '.($donnees['annee_scolaire'] ?? $existant?->annee_scolaire);

        return array_filter($donnees, fn ($valeur) => $valeur !== null);
    }
}
