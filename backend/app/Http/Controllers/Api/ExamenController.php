<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Classe;
use App\Models\Etudiant;
use App\Services\BulletinService;
use App\Services\TeacherScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExamenController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin,proviseur,censeur,enseignant');
    }

    private const NIVEAUX_EXAMEN = ['3ème', 'Terminale'];

    public function index(Request $request): JsonResponse
    {
        $regles = \App\Models\Regle::first();
        $seuil = $regles->seuil_admission ?? 10;
        $classeIds = TeacherScope::classeIds($request->user());

        $classes = Classe::whereIn('niveau', self::NIVEAUX_EXAMEN)
            ->when($classeIds !== null, fn ($q) => $q->whereIn('id', $classeIds))
            ->withCount('etudiants')
            ->orderBy('niveau')
            ->orderBy('nom')
            ->get();

        $etudiants = Etudiant::whereHas('classe', fn ($q) => $q->whereIn('niveau', self::NIVEAUX_EXAMEN))
            ->when($classeIds !== null, fn ($q) => $q->whereIn('classe_id', $classeIds))
            ->with('classe:id,nom,niveau')
            ->with(['notes' => function ($q) { $q->where('trimestre', 3); }])
            ->get();

        $eleves = [];
        $admis = 0;
        $total = 0;

        foreach ($etudiants as $etudiant) {
            if ($etudiant->notes->isEmpty()) {
                continue;
            }

            $moyenne = $etudiant->notes->avg('valeur') ?? 0;
            $total++;

            $admis_status = $moyenne >= $seuil ? 'admis' : 'non_admis';
            if ($admis_status === 'admis') {
                $admis++;
            }

            $eleves[] = [
                'id' => $etudiant->id,
                'matricule' => $etudiant->matricule,
                'nom' => $etudiant->nom,
                'prenom' => $etudiant->prenom,
                'classe' => $etudiant->classe ? $etudiant->classe->only(['id', 'nom', 'niveau']) : null,
                'moyenne' => round($moyenne, 2),
                'decision' => [
                    'statut' => $admis_status,
                    'seuil' => $seuil,
                ],
            ];
        }

        usort($eleves, function ($a, $b) {
            $classeCmp = strcmp($a['classe']['nom'] ?? '', $b['classe']['nom'] ?? '');

            return $classeCmp !== 0 ? $classeCmp : ($b['moyenne'] <=> $a['moyenne']);
        });

        return response()->json([
            'classes_examen' => $classes,
            'statistiques' => [
                'total_eleves' => $total,
                'admis' => $admis,
                'non_admis' => $total - $admis,
                'taux_admission' => $total ? round(($admis / $total) * 100, 1) : 0,
                'seuil' => $seuil,
            ],
            'eleves' => $eleves,
        ]);
    }
}
