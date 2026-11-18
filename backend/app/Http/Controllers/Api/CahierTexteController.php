<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CahierTexte;
use App\Services\AuditLogger;

use App\Services\TeacherScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Cahier de texte numérique.
 * - L'enseignant saisit et modifie uniquement ses propres entrées, sur ses classes et matières.
 * - La direction consulte tout l'établissement.
 * - Le parent consulte les entrées des classes de ses enfants.
 */
class CahierTexteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $requete = CahierTexte::with(['classe:id,nom', 'matiere:id,nom,couleur', 'enseignant:id,nom,prenom'])
            ->orderByDesc('date')
            ->orderByDesc('id');

        if ($user->role === 'parent') {
            $classes = $user->enfants()->pluck('etudiants.classe_id')->filter()->unique()->values();
            $requete->whereIn('classe_id', $classes);
        } elseif ($user->role === 'enseignant') {
            $requete->where('enseignant_id', $user->enseignant_id);
        }

        if ($request->filled('classe_id')) {
            $requete->where('classe_id', (int) $request->query('classe_id'));
        }

        return response()->json($requete->limit(200)->get());
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->enseignant_id, 422, "Ce compte n'est pas rattaché à une fiche enseignant.");

        $data = $this->valider($request);
        $this->verifierPerimetre($user, $data['classe_id'], $data['matiere_id']);

        $entree = CahierTexte::create($data + ['enseignant_id' => $user->enseignant_id]);

        AuditLogger::log($user, 'cahier_texte', 'cahiers_texte', $entree->id, "Entrée du {$entree->date->format('d/m/Y')} publiée.");

        return response()->json(['message' => 'Entrée publiée au cahier de texte.', 'entree' => $entree->load(['classe:id,nom', 'matiere:id,nom,couleur'])], 201);
    }

    public function update(Request $request, CahierTexte $cahier): JsonResponse
    {
        $user = $request->user();
        abort_unless($cahier->enseignant_id === $user->enseignant_id, 403, 'Vous ne pouvez modifier que vos propres entrées.');

        $data = $this->valider($request);
        $this->verifierPerimetre($user, $data['classe_id'], $data['matiere_id']);

        $cahier->update($data);

        return response()->json(['message' => 'Entrée mise à jour.', 'entree' => $cahier->fresh(['classe:id,nom', 'matiere:id,nom,couleur'])]);
    }

    public function destroy(Request $request, CahierTexte $cahier): JsonResponse
    {
        abort_unless($cahier->enseignant_id === $request->user()->enseignant_id, 403, 'Vous ne pouvez supprimer que vos propres entrées.');

        $cahier->delete();

        return response()->json(['message' => 'Entrée supprimée.']);
    }

    private function valider(Request $request): array
    {
        return $request->validate([
            'classe_id' => 'required|integer|exists:classes,id',
            'matiere_id' => 'required|integer|exists:matieres,id',
            'date' => 'required|date',
            'contenu' => 'required|string|max:5000',
            'devoirs' => 'nullable|string|max:2000',
        ]);
    }

    /** Un enseignant ne publie que dans ses classes et ses matières. */
    private function verifierPerimetre($user, int $classeId, int $matiereId): void
    {
        $classes = TeacherScope::classeIds($user);
        abort_if($classes !== null && ! in_array($classeId, $classes, true), 403, "Cette classe ne vous est pas affectée.");
        abort_unless(TeacherScope::canAccessMatiere($user, $matiereId), 403, "Cette matière ne vous est pas affectée.");
    }
}
