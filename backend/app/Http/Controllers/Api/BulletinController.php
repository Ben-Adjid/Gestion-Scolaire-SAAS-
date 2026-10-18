<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Classe;
use App\Models\Etudiant;
use App\Services\BulletinService;
use App\Services\TeacherScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BulletinController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin,proviseur,censeur,cpe');
    }

    public function show(Etudiant $etudiant, Request $request): JsonResponse
    {
        if (TeacherScope::isTeacherRole($request->user())) {
            return response()->json([
                'message' => "Les bulletins ne sont pas accessibles aux enseignants.",
            ], 403);
        }

        if (! TeacherScope::canAccessClasse($request->user(), (int) $etudiant->classe_id)) {
            return response()->json([
                'message' => "Vous ne pouvez consulter que les bulletins des élèves de vos classes.",
            ], 403);
        }

        $trimestre = $request->boolean('annuel')
            ? null
            : (int) $request->query('trimestre', 1);

        $convocations = $etudiant->convocations()->orderBy('date')->get();

        return response()->json(array_merge(
            app(BulletinService::class)->generateBulletin($etudiant, $trimestre),
            [
                'nb_convocations' => $convocations->count(),
                'motifs_convocations' => $convocations->pluck('motif')->values(),
            ]
        ));
    }

    public function classe(Classe $classe, Request $request): JsonResponse
    {
        if (TeacherScope::isTeacherRole($request->user())) {
            return response()->json([
                'message' => "Les bulletins ne sont pas accessibles aux enseignants.",
            ], 403);
        }

        $trimestre = $request->boolean('annuel')
            ? null
            : (int) $request->query('trimestre', 1);

        $service = app(BulletinService::class);

        $bulletins = $classe->etudiants()
            ->orderBy('nom')
            ->get()
            ->map(function (Etudiant $etudiant) use ($service, $trimestre) {
                return $service->generateBulletin($etudiant, $trimestre);
            });

        return response()->json([
            'classe' => $classe->only(['id', 'nom', 'niveau']),
            'bulletins' => $bulletins,
        ]);
    }
}
