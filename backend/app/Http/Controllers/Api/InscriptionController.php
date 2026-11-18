<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\InscriptionRequest;
use App\Models\Inscription;
use App\Models\Etudiant;
use App\Models\Classe;
use App\Models\Etablissement;
use Illuminate\Validation\Rule;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InscriptionController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin,proviseur,secretaire')->only(['index', 'show', 'store', 'update', 'destroy']);
    }

    public function index(Request $request): JsonResponse
    {
        $query = Inscription::with(['etudiant', 'classe'])
            ->orderByDesc('created_at');

        if ($request->query('annee_scolaire')) {
            $query->where('annee_scolaire', $request->query('annee_scolaire'));
        }

        if ($request->query('type')) {
            $query->where('type', $request->query('type'));
        }

        $inscriptions = $query->paginate((int) $request->query('per_page', 30));

        return response()->json($inscriptions);
    }

    public function show(Inscription $inscription): JsonResponse
    {
        return response()->json($inscription->load(['etudiant', 'classe']));
    }

    /** Une inscription se modifie et se supprime seulement pendant l'année scolaire en cours : les années passées sont figées. */
    private function refuserSiAnneePassee(Inscription $inscription): ?JsonResponse
    {
        $anneeEcole = Etablissement::find($inscription->etablissement_id)?->annee_scolaire;
        if ($anneeEcole && $inscription->annee_scolaire !== $anneeEcole) {
            return response()->json([
                'message' => "Cette inscription appartient à l’année {$inscription->annee_scolaire}. Les années passées ne sont plus modifiables.",
            ], 422);
        }

        return null;
    }

    public function update(Request $request, Inscription $inscription): JsonResponse
    {
        if ($refus = $this->refuserSiAnneePassee($inscription)) {
            return $refus;
        }

        $data = $request->validate([
            'classe_id' => ['required', 'integer', 'exists:classes,id'],
            'date_inscription' => ['required', 'date'],
            'statut' => ['required', Rule::in(['active', 'terminee', 'annulee'])],
        ]);

        // Changer de classe vers un niveau supérieur suit la même règle qu'une réinscription.
        $ancienne = Classe::find($inscription->classe_id);
        $cible = Classe::find($data['classe_id']);
        if ((int) $data['classe_id'] !== (int) $inscription->classe_id && $cible) {
            $motif = app(\App\Services\BulletinService::class)->motifRefusPassage(
                Etudiant::with('classe')->findOrFail($inscription->etudiant_id),
                $cible->niveau,
                $ancienne?->niveau,
            );
            if ($motif) {
                return response()->json(['message' => $motif, 'errors' => ['classe_id' => [$motif]]], 422);
            }
        }

        $inscription->update($data);
        AuditLogger::log($request->user(), 'modification', 'inscriptions', $inscription->id, 'Modification de l’inscription');

        return response()->json([
            'message' => 'Inscription mise à jour avec succès.',
            'inscription' => $inscription->fresh()->load(['etudiant', 'classe']),
        ]);
    }

    public function destroy(Request $request, Inscription $inscription): JsonResponse
    {
        if ($refus = $this->refuserSiAnneePassee($inscription)) {
            return $refus;
        }

        // Des notes ou des paiements de cette année rattachent l'élève à son inscription : on ne supprime pas.
        $notes = \App\Models\Note::where('etudiant_id', $inscription->etudiant_id)
            ->where('annee_scolaire', $inscription->annee_scolaire)->where('statut', 'valide')->exists();
        $paiements = \App\Models\Paiement::where('etudiant_id', $inscription->etudiant_id)->where('statut', 'completed')->exists();
        if ($notes || $paiements) {
            return response()->json([
                'message' => 'Cet élève a des notes validées ou des paiements pour cette année : son inscription ne peut plus être supprimée.',
            ], 422);
        }

        $inscription->delete();
        AuditLogger::log($request->user(), 'suppression', 'inscriptions', $inscription->id, 'Suppression de l’inscription');

        return response()->json(['message' => 'Inscription supprimée.']);
    }

    public function store(InscriptionRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Réinscription dans un niveau supérieur : réservée aux élèves admis.
        if (($data['type'] ?? null) === 'ancien' && ! empty($data['etudiant_id'])) {
            $cible = Classe::find($data['classe_id']);
            $motif = app(\App\Services\BulletinService::class)->motifRefusPassage(
                Etudiant::with('classe')->findOrFail($data['etudiant_id']),
                $cible?->niveau,
            );
            if ($motif) {
                return response()->json(['message' => $motif, 'errors' => ['classe_id' => [$motif]]], 422);
            }
        }

        $compteParent = null;
        $inscription = DB::transaction(function () use ($data, $request, &$compteParent) {
            if (empty($data['etudiant_id'])) {
                $etudiant = Etudiant::create([
                    'nom' => $data['nom'],
                    'prenom' => $data['prenom'],
                    'date_naissance' => $data['date_naissance'] ?? null,
                    'genre' => $data['genre'] ?? null,
                    'classe_id' => $data['classe_id'],
                    'annee_scolaire' => $data['annee_scolaire'],
                ]);
                $data['etudiant_id'] = $etudiant->id;
            }

            $inscription = Inscription::create(collect($data)->only([
                'etudiant_id', 'classe_id', 'annee_scolaire', 'type', 'date_inscription', 'statut',
            ])->all());

            // Compte parent : créé, ou parent existant relié (frère/sœur, réinscription). Une erreur annule l'inscription.
            $compteParent = app(\App\Services\CompteParentService::class)
                ->rattacher(Etudiant::findOrFail($data['etudiant_id']), $data['parent'] ?? null, $request->user());

            return $inscription;
        });

        AuditLogger::log($request->user(), 'création', 'inscriptions', $inscription->id,
            "Inscription d'un élève en {$inscription->classe->nom}");

        return response()->json([
            'message' => 'Inscription enregistrée avec succès.',
            'inscription' => $inscription->load(['etudiant', 'classe']),
            'compte_parent' => $compteParent,
        ], 201);
    }

    /**
     * Affecter automatiquement les élèves en classes
     */
    public function assignClasses(Request $request): JsonResponse
    {
        $annee = $request->query('annee_scolaire', now()->year);
        
        // Récupérer les inscriptions non encore affectées
        $unassigned = Inscription::where('annee_scolaire', $annee)
            ->whereNull('classe_id')
            ->get();

        $assigned = 0;
        foreach ($unassigned as $inscription) {
            // Algorithme d'équilibre: trouver la classe avec le moins d'élèves
            $classe = Classe::where('cycle', $this->getCycle($inscription->etudiant))
                ->withCount('etudiants')
                ->orderBy('etudiants_count')
                ->first();

            if ($classe) {
                $inscription->update(['classe_id' => $classe->id]);
                $assigned++;
            }
        }

        AuditLogger::log($request->user(), 'affectation_groupe', 'inscriptions', null,
            "Affectation automatique de $assigned élèves");

        return response()->json([
            'message' => "$assigned élèves ont été affectés aux classes.",
            'count' => $assigned,
        ]);
    }

    private function getCycle($student)
    {
        // Déterminer le cycle en fonction de l'âge ou du niveau
        return 'collège'; // À adapter
    }
}
