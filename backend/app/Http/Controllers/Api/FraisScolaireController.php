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
        $this->middleware('role:admin,proviseur,gestionnaire,agent_comptable')->only(['index', 'stats', 'show', 'paiements']);
        $this->middleware('role:admin,gestionnaire,agent_comptable')->only(['store', 'update', 'bulkInit', 'addPaiement']);
    }

    public function index(Request $request): JsonResponse
    {
        $query = FraisScolaire::query()->with('etudiant:id,nom,prenom,matricule');

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

    public function stats(): JsonResponse
    {
        $montantTotal = (float) FraisScolaire::sum('montant');
        $montantPaye = (float) Paiement::where('statut', 'completed')->sum('montant');

        return response()->json([
            'montant_total' => $montantTotal,
            'montant_paye' => $montantPaye,
            'reste' => $montantTotal - $montantPaye,
            'effectif' => FraisScolaire::distinct('etudiant_id')->count('etudiant_id'),
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
        return response()->json($frais->load(['etudiant:id,nom,prenom,matricule', 'paiements']));
    }

    public function update(Request $request, FraisScolaire $frais): JsonResponse
    {
        $validated = $request->validate([
            'libelle' => 'nullable|string|max:255',
            'montant_total' => 'nullable|numeric|min:0',
            'date_echeance' => 'nullable|date',
            'statut' => 'nullable|in:pending,partial,paid,overdue',
        ]);

        $frais->update($this->donneesFrais($validated, $frais));

        AuditLogger::log($request->user(), 'update', 'FraisScolaire', $frais->id, "Mise à jour des frais");

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
        return response()->json($frais->paiements()->orderByDesc('date_paiement')->get());
    }

    public function addPaiement(Request $request, FraisScolaire $frais): JsonResponse
    {
        $validated = $request->validate([
            'montant' => 'required|numeric|min:0.01',
            'date_paiement' => 'required|date',
            'mode' => 'required|string|max:50',
            'reference' => 'nullable|string|max:100',
        ]);

        $frais->loadMissing('paiements');
        if ($validated['montant'] > $frais->reste) {
            return response()->json([
                'message' => "Le montant dépasse le reste à payer ({$frais->reste} FCFA).",
            ], 422);
        }

        $paiement = Paiement::create([
            'frais_scolaire_id' => $frais->id,
            'etudiant_id' => $frais->etudiant_id,
            'montant' => $validated['montant'],
            'date_paiement' => $validated['date_paiement'],
            'mode_paiement' => $validated['mode'],
            'reference' => $validated['reference'] ?? null,
            'statut' => 'completed',
        ]);

        AuditLogger::log($request->user(), 'create', 'Paiement', $paiement->id, "Paiement de {$paiement->montant} FCFA");

        return response()->json([
            'paiement' => $paiement,
            'frais' => $frais->fresh()->load('etudiant:id,nom,prenom,matricule'),
        ], 201);
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
