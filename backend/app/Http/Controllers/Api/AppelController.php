<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AppelRequest;
use App\Models\Absence;
use App\Models\Appel;
use App\Models\Etudiant;
use App\Services\TeacherScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AppelController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin,censeur,cpe,enseignant')->except(['confirmer', 'enAttente']);
        $this->middleware('role:admin,censeur,cpe')->only(['confirmer', 'enAttente']);
    }

    public function index(Request $request): JsonResponse
    {
        $classeId = $request->query('classe_id');
        $date = $request->query('date');

        if (! $classeId || ! $date) {
            return response()->json(null);
        }

        $appel = Appel::where('classe_id', $classeId)
            ->where('date', $date)
            ->with('user:id,name', 'validePar:id,name', 'confirmePar:id,name')
            ->first();

        return response()->json($appel);
    }

    public function confirmer(Request $request, Appel $appel): JsonResponse
    {
        if ($appel->statut !== 'valide') {
            return response()->json([
                'message' => "Cet appel n'a pas encore été validé par l'enseignant.",
            ], 422);
        }

        $appel->update([
            'statut' => 'confirme',
            'confirme_par' => $request->user()->id,
            'confirme_at' => now(),
        ]);

        return response()->json(['message' => "Appel confirmé. Les absences sont maintenant comptabilisées."]);
    }

    public function enAttente(Request $request): JsonResponse
    {
        $appels = Appel::where('statut', 'valide')
            ->with(['classe:id,nom,niveau', 'user:id,name'])
            ->orderByDesc('date')
            ->get()
            ->map(function (Appel $appel) {
                $etudiantIds = Etudiant::where('classe_id', $appel->classe_id)->pluck('id');

                $appel->nb_absents = Absence::whereIn('etudiant_id', $etudiantIds)
                    ->where('date', $appel->date->format('Y-m-d'))
                    ->count();

                return $appel;
            });

        return response()->json($appels);
    }

    public function store(AppelRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();
        $valider = $request->boolean('valider');

        if (! TeacherScope::canAccessClasse($user, (int) $data['classe_id'])) {
            return response()->json([
                'message' => "Vous ne pouvez faire l'appel que dans vos classes.",
            ], 403);
        }

        $date = $data['date'];
        $classeId = (int) $data['classe_id'];

        $appel = Appel::where('classe_id', $classeId)->where('date', $date)->first();

        if ($appel && $appel->statut === 'valide') {
            return response()->json([
                'message' => "Cet appel a déjà été validé et verrouillé.",
            ], 422);
        }

        $etudiantIds = Etudiant::where('classe_id', $classeId)->pluck('id');

        DB::transaction(function () use ($data, $date, $etudiantIds, $classeId, $user, $valider, $appel) {
            Absence::whereIn('etudiant_id', $etudiantIds)
                ->where('date', $date)
                ->delete();

            foreach ($data['presences'] as $item) {
                if ($item['statut'] === 'present') {
                    continue;
                }

                Absence::create([
                    'etudiant_id' => $item['etudiant_id'],
                    'date' => $date,
                    'type' => $item['statut'] === 'retard' ? 'retard' : 'absence',
                    'justifie' => false,
                ]);
            }

            $appelData = [
                'classe_id' => $classeId,
                'date' => $date,
                'user_id' => $user->id,
                'statut' => $valider ? 'valide' : 'brouillon',
            ];

            if ($valider) {
                $appelData['valide_par'] = $user->id;
                $appelData['valide_at'] = now();
            }

            if ($appel) {
                $appel->update($appelData);
            } else {
                Appel::create($appelData);
            }
        });

        $nbAbsents = collect($data['presences'])->where('statut', '!=', 'present')->count();

        return response()->json([
            'message' => $valider
                ? "Appel validé et verrouillé : {$nbAbsents} absence(s)/retard(s) noté(s)."
                : "Appel enregistré (brouillon) : {$nbAbsents} absence(s)/retard(s) noté(s).",
            'valide' => $valider,
        ]);
    }
}
