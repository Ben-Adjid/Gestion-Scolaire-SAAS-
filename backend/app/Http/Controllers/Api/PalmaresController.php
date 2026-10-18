<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Classe;
use App\Models\Etudiant;
use App\Services\BulletinService;
use App\Services\TeacherScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PalmaresController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin,proviseur,censeur,cpe');
    }

    public function index(Request $request): JsonResponse
    {
        if (TeacherScope::isTeacherRole($request->user())) {
            return response()->json([
                'message' => "Le tableau d'honneur n'est pas accessible aux enseignants.",
            ], 403);
        }

        $trimestre = (int) $request->query('trimestre', 1);
        $classeId = $request->query('classe_id');
        $cycle = $request->query('cycle');

        // Récupérer les seuils depuis la table regles
        $regles = \App\Models\Regle::first();
        $nb = $regles->nb_tableau_honneur ?? 5;
        $seuilMinimum = $regles->seuil_bien ?? 12;

        $classes = Classe::query()
            ->when($classeId, fn ($q) => $q->where('id', $classeId))
            ->when(! $classeId && ($cycle === 'college' || $cycle === 'lycee'), function ($q) use ($cycle) {
                $niveaux = $cycle === 'college'
                    ? ['6ème', '5ème', '4ème', '3ème']
                    : ['Seconde', 'Première', 'Terminale'];

                $q->whereIn('niveau', $niveaux);
            })
            ->orderBy('niveau')
            ->orderBy('nom')
            ->get();

        $tableau = [];

        foreach ($classes as $classe) {
            $etudiants = $classe->etudiants()
                ->with(['notes' => function ($q) use ($trimestre) {
                    $q->where('trimestre', $trimestre);
                }])
                ->get();

            if ($etudiants->isEmpty()) {
                continue;
            }

            $eleves = [];

            foreach ($etudiants as $etudiant) {
                if ($etudiant->notes->isEmpty()) {
                    continue;
                }

                $moyenne = $etudiant->notes->avg('valeur') ?? 0;

                if ($moyenne < $seuilMinimum) {
                    continue;
                }

                $eleves[] = [
                    'rang' => null,
                    'moyenne' => round($moyenne, 2),
                    'etudiant' => [
                        'id' => $etudiant->id,
                        'matricule' => $etudiant->matricule,
                        'nom' => $etudiant->nom,
                        'prenom' => $etudiant->prenom,
                        'genre' => $etudiant->genre,
                    ],
                ];

            }

            usort($eleves, fn ($a, $b) => $b['moyenne'] <=> $a['moyenne']);
            $eleves = array_slice($eleves, 0, $nb);
            foreach ($eleves as $i => $eleve) {
                $eleves[$i]['rang'] = $i + 1;
            }

            if (! empty($eleves)) {
                $tableau[] = [
                    'classe' => $classe->only(['id', 'nom', 'niveau']),
                    'eleves' => $eleves,
                ];
            }
        }

        return response()->json([
            'trimestre' => $trimestre,
            'nb_tableau_honneur' => $nb,
            'tableau_honneur' => $tableau,
        ]);
    }
}
