<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\InscriptionRequest;
use App\Models\Inscription;
use App\Models\Etudiant;
use App\Models\Classe;
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

    public function store(InscriptionRequest $request): JsonResponse
    {
        $data = $request->validated();

        $inscription = DB::transaction(function () use ($data) {
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

            return Inscription::create(collect($data)->only([
                'etudiant_id', 'classe_id', 'annee_scolaire', 'type', 'date_inscription', 'statut',
            ])->all());
        });

        AuditLogger::log($request->user(), 'création', 'inscriptions', $inscription->id,
            "Inscription d'un élève en {$inscription->classe->nom}");

        return response()->json([
            'message' => 'Inscription enregistrée avec succès.',
            'inscription' => $inscription->load(['etudiant', 'classe']),
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
