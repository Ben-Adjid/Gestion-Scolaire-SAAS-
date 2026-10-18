<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Alerte;
use App\Models\Classe;
use App\Models\Enseignant;
use App\Models\Etudiant;
use App\Models\Matiere;
use App\Models\Note;
use App\Services\TeacherScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    private const COLLEGE_NIVEAUX = ['6ème', '5ème', '4ème', '3ème'];

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $etablissementId = $user->etablissement_id;

        $etudiants = Etudiant::where('etablissement_id', $etablissementId);

        $classeIds = TeacherScope::classeIds($user);
        if ($classeIds !== null) {
            $etudiants->whereIn('classe_id', $classeIds);
        }

        $matiereIds = TeacherScope::matiereIds($user);
        $notes = Note::whereHas('etudiant', fn ($q) => $q->where('etablissement_id', $etablissementId));
        if ($matiereIds !== null) {
            $notes->whereIn('matiere_id', $matiereIds)
                ->whereHas('etudiant', fn ($q) => $q->whereIn('classe_id', $classeIds));
        }

        $classes = Classe::where('etablissement_id', $etablissementId)
            ->when($classeIds !== null, fn ($q) => $q->whereIn('id', $classeIds))
            ->withCount('etudiants')
            ->orderBy('niveau')
            ->orderBy('nom')
            ->get(['id', 'nom', 'niveau']);

        $stats = [
            'etudiants' => (clone $etudiants)->count(),
            'classes' => $classes->count(),
            'enseignants' => Enseignant::where('etablissement_id', $etablissementId)->count(),
            'matieres' => $matiereIds !== null
                ? count($matiereIds)
                : Matiere::where('etablissement_id', $etablissementId)->count(),
            'notes' => $notes->count(),
        ];

        $cycle = ['college' => 0, 'lycee' => 0];
        foreach ($classes as $classe) {
            $cycle[in_array($classe->niveau, self::COLLEGE_NIVEAUX, true) ? 'college' : 'lycee'] += $classe->etudiants_count;
        }

        $genres = (clone $etudiants)->selectRaw('genre, COUNT(*) as total')->groupBy('genre')->pluck('total', 'genre');

        $voitEleves = $user->hasAnyRole(['admin', 'proviseur', 'censeur', 'cpe', 'enseignant', 'secretaire', 'gestionnaire', 'agent_comptable']);

        $topEleves = [];
        if ($voitEleves && ! TeacherScope::isTeacherRole($user)) {
            $topEleves = Etudiant::where('etablissement_id', $etablissementId)
                ->whereHas('notes')
                ->withAvg('notes', 'valeur')
                ->with('classe:id,nom')
                ->orderByDesc('notes_avg_valeur')
                ->limit(5)
                ->get()
                ->map(fn (Etudiant $e) => [
                    'id' => $e->id,
                    'nom' => $e->nom,
                    'prenom' => $e->prenom,
                    'classe' => $e->classe?->nom,
                    'moyenne' => round((float) $e->notes_avg_valeur, 2),
                ])
                ->values();
        }

        $derniers = $voitEleves ? (clone $etudiants)
            ->with('classe:id,nom')
            ->latest('id')
            ->limit(5)
            ->get(['id', 'nom', 'prenom', 'matricule', 'classe_id'])
            ->map(fn (Etudiant $e) => [
                'id' => $e->id,
                'nom' => $e->nom,
                'prenom' => $e->prenom,
                'matricule' => $e->matricule,
                'classe' => ['nom' => $e->classe?->nom],
            ]) : [];

        return response()->json([
            'stats' => $stats,
            'repartition_par_classe' => $classes->map(fn (Classe $c) => [
                'id' => $c->id,
                'nom' => $c->nom,
                'etudiants_count' => $c->etudiants_count,
            ])->values(),
            'repartition_par_genre' => [
                'masculin' => (int) ($genres['M'] ?? 0),
                'feminin' => (int) ($genres['F'] ?? 0),
            ],
            'repartition_par_cycle' => $cycle,
            'top_eleves' => $topEleves,
            'derniers_etudiants' => $derniers,
        ]);
    }

    /**
     * Obtenir les alertes de l'établissement
     */
    public function alerts(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $query = Alerte::where('etablissement_id', $user->etablissement_id)
            ->with('etudiant')
            ->orderByDesc('created_at');

        if ($request->query('severity')) {
            $query->where('severity', $request->query('severity'));
        }

        if (!$request->query('show_acknowledged')) {
            $query->where('acknowledged', false);
        }

        $alerts = $query->paginate((int) $request->query('per_page', 20));

        return response()->json($alerts);
    }

    /**
     * Marquer une alerte comme vérifiée
     */
    public function acknowledgeAlert(Request $request, Alerte $alert): JsonResponse
    {
        $alert->update(['acknowledged' => true]);

        return response()->json([
            'message' => 'Alerte marquée comme vérifiée.',
            'alert' => $alert,
        ]);
    }
}
