<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Classe;
use App\Models\Enseignant;
use App\Models\Etudiant;
use App\Services\TeacherScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Recherche globale (élèves, classes, enseignants) dans l'établissement de l'utilisateur.
 * Les modèles appliquent déjà le cloisonnement par établissement ; un enseignant ne voit
 * que les élèves et les classes qui lui sont affectés.
 */
class RechercheController extends Controller
{
    private const LIMITE = 6;

    public function index(Request $request): JsonResponse
    {
        $terme = trim((string) $request->query('q', ''));
        if (mb_strlen($terme) < 2) {
            return response()->json(['eleves' => [], 'classes' => [], 'enseignants' => []]);
        }

        $motif = '%'.addcslashes($terme, '%_\\').'%';
        $user = $request->user();
        $classes = TeacherScope::classeIds($user); // null = accès à toutes les classes

        $eleves = Etudiant::with('classe:id,nom')
            ->where(function ($q) use ($motif) {
                $q->where('nom', 'like', $motif)->orWhere('prenom', 'like', $motif)->orWhere('matricule', 'like', $motif);
            })
            ->when($classes !== null, fn ($q) => $q->whereIn('classe_id', $classes))
            ->orderBy('nom')
            ->limit(self::LIMITE)
            ->get(['id', 'nom', 'prenom', 'matricule', 'classe_id'])
            ->map(fn ($e) => [
                'id' => $e->id,
                'libelle' => trim($e->prenom.' '.$e->nom),
                'detail' => trim(($e->classe?->nom ?? 'Sans classe').($e->matricule ? ' · '.$e->matricule : '')),
                'lien' => '/etudiants',
            ])->values();

        $classesTrouvees = Classe::where('nom', 'like', $motif)
            ->when($classes !== null, fn ($q) => $q->whereIn('id', $classes))
            ->orderBy('nom')
            ->limit(self::LIMITE)
            ->get(['id', 'nom', 'niveau'])
            ->map(fn ($c) => ['id' => $c->id, 'libelle' => $c->nom, 'detail' => $c->niveau, 'lien' => '/classes'])
            ->values();

        $enseignants = Enseignant::where(function ($q) use ($motif) {
                $q->where('nom', 'like', $motif)->orWhere('prenom', 'like', $motif);
            })
            ->orderBy('nom')
            ->limit(self::LIMITE)
            ->get(['id', 'nom', 'prenom', 'specialite'])
            ->map(fn ($e) => [
                'id' => $e->id,
                'libelle' => trim($e->prenom.' '.$e->nom),
                'detail' => $e->specialite ?? '',
                'lien' => '/enseignants',
            ])->values();

        return response()->json([
            'eleves' => $eleves,
            'classes' => $classesTrouvees,
            'enseignants' => $enseignants,
        ]);
    }
}
