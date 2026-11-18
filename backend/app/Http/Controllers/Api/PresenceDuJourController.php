<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Absence;
use App\Models\Classe;
use App\Models\Etudiant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Présence du jour en temps réel, par classe (formule VIP et plus). */
class PresenceDuJourController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $aujourdhui = today()->toDateString();

        $absences = Absence::with('etudiant:id,nom,prenom,classe_id')
            ->whereDate('date', $aujourdhui)
            ->whereHas('etudiant')
            ->get();

        $parClasse = $absences->groupBy(fn (Absence $a) => $a->etudiant->classe_id);

        $classes = Classe::withCount('etudiants')->orderBy('nom')->get(['id', 'nom'])->map(function (Classe $classe) use ($parClasse) {
            $absencesClasse = $parClasse->get($classe->id, collect());
            $absents = $absencesClasse->where('type', 'absence');
            $retards = $absencesClasse->where('type', 'retard');

            return [
                'id' => $classe->id,
                'nom' => $classe->nom,
                'effectif' => $classe->etudiants_count,
                'absents' => $absents->count(),
                'retards' => $retards->count(),
                'non_justifies' => $absencesClasse->where('justifie', false)->count(),
                'liste' => $absencesClasse->map(fn (Absence $a) => [
                    'nom' => $a->etudiant->prenom.' '.$a->etudiant->nom,
                    'type' => $a->type,
                    'justifie' => (bool) $a->justifie,
                ])->values(),
            ];
        })->values();

        return response()->json([
            'date' => $aujourdhui,
            'total_eleves' => Etudiant::count(),
            'absents' => $absences->where('type', 'absence')->count(),
            'retards' => $absences->where('type', 'retard')->count(),
            'classes' => $classes,
        ]);
    }
}
