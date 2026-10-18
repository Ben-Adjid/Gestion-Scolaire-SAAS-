<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PointageRequest;
use App\Models\EmploiDuTemps;
use App\Models\Enseignant;
use App\Models\Etablissement;
use App\Models\Pointage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PointageController extends Controller
{
    private const FENETRE_MINUTES = 15;

    public function __construct()
    {
        $this->middleware('role:enseignant')->only(['aujourdhui', 'store']);
        $this->middleware('role:admin,proviseur,censeur')->only(['index', 'stats']);
    }

    public function aujourdhui(Request $request): JsonResponse
    {
        $user = $request->user();
        $enseignantId = $user->enseignant_id ?? Enseignant::where('email', $user->email)->first()?->id;

        if (! $enseignantId) {
            return response()->json(['message' => "Votre compte n'est pas lié à un enseignant."], 422);
        }

        $now = now();
        $jour = $now->dayOfWeekIso;

        $cours = EmploiDuTemps::where('enseignant_id', $enseignantId)
            ->where('jour', $jour)
            ->with(['classe:id,nom,niveau', 'matiere:id,nom,couleur', 'creneau'])
            ->get();

        $pointages = Pointage::where('enseignant_id', $enseignantId)
            ->whereDate('date', $now->toDateString())
            ->get()
            ->keyBy('emploi_du_temps_id');

        $result = $cours->filter(fn (EmploiDuTemps $c) => $c->heure_debut !== null)
            ->map(function (EmploiDuTemps $cours) use ($pointages, $now) {
                $start = Carbon::today()->setTimeFromTimeString($cours->heure_debut);
                $min = $start->copy()->subMinutes(self::FENETRE_MINUTES);
                $max = $start->copy()->addMinutes(self::FENETRE_MINUTES);

                $pointage = $pointages->get($cours->id);

                return [
                    'emploi_du_temps_id' => $cours->id,
                    'classe' => $cours->classe?->nom,
                    'matiere' => $cours->matiere?->nom,
                    'couleur' => $cours->matiere?->couleur,
                    'heure_debut' => $cours->heure_debut,
                    'heure_fin' => $cours->heure_fin,
                    'pointage' => $pointage ? ['statut' => $pointage->statut, 'heure' => $pointage->heure] : null,
                    'pointable' => ! $pointage && $now->between($min, $max),
                    'fenetre' => $min->format('H:i').' — '.$max->format('H:i'),
                ];
            });

        return response()->json([
            'code' => Enseignant::find($enseignantId)?->code_pointe,
            'cours' => $result,
        ]);
    }

    public function store(PointageRequest $request): JsonResponse
    {
        $enseignantId = $request->user()->enseignant_id;

        if (! $enseignantId) {
            return response()->json(['message' => "Votre compte n'est pas lié à un enseignant."], 422);
        }

        $now = now();
        $jour = $now->dayOfWeekIso;

        $cours = EmploiDuTemps::where('enseignant_id', $enseignantId)
            ->where('id', $request->input('emploi_du_temps_id'))
            ->where('jour', $jour)
            ->first();

        if (! $cours || ! $cours->heure_debut) {
            return response()->json(['message' => "Ce cours ne vous concerne pas aujourd'hui."], 422);
        }

        $start = Carbon::today()->setTimeFromTimeString($cours->heure_debut);
        $min = $start->copy()->subMinutes(self::FENETRE_MINUTES);
        $max = $start->copy()->addMinutes(self::FENETRE_MINUTES);

        if ($now->lt($min)) {
            return response()->json([
                'message' => "Trop tôt. Vous pourrez pointer à partir de {$min->format('H:i')}.",
            ], 422);
        }

        if ($now->gt($max)) {
            return response()->json([
                'message' => "Fenêtre de pointage dépassée ({$max->format('H:i')}).",
            ], 422);
        }

        if (Pointage::where('enseignant_id', $enseignantId)
            ->where('emploi_du_temps_id', $cours->id)
            ->whereDate('date', $now->toDateString())
            ->exists()) {
            return response()->json(['message' => 'Vous avez déjà pointé pour ce cours.'], 422);
        }

        $distance = null;

        if (! config('pointage.mode_test')) {
            $etab = Etablissement::first();

            if (! $etab || ! $etab->latitude || ! $etab->longitude) {
                return response()->json(['message' => "Géolocalisation de l'établissement non configurée."], 422);
            }

            if (! $request->filled('latitude') || ! $request->filled('longitude')) {
                return response()->json(['message' => 'Position GPS requise pour le pointage.'], 422);
            }

            $distance = $this->haversine(
                $etab->latitude,
                $etab->longitude,
                (float) $request->input('latitude'),
                (float) $request->input('longitude')
            );

            if ($distance > ($etab->rayon_pointage ?? 150)) {
                return response()->json(['message' => "Vous devez être dans l'établissement pour pointer."], 422);
            }
        }

        $statut = $now->gt($start) ? 'retard' : 'a_l_heure';

        $pointage = Pointage::create([
            'enseignant_id' => $enseignantId,
            'emploi_du_temps_id' => $cours->id,
            'date' => $now->toDateString(),
            'heure' => $now->toTimeString(),
            'statut' => $statut,
            'latitude' => $request->input('latitude'),
            'longitude' => $request->input('longitude'),
            'distance_metres' => $distance !== null ? round($distance) : null,
        ]);

        return response()->json([
            'message' => $statut === 'retard'
                ? 'Pointage enregistré (en retard).'
                : "Pointage enregistré (à l'heure).",
            'pointage' => $pointage,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $date = $request->query('date', now()->toDateString());

        $pointages = Pointage::whereDate('date', $date)
            ->with('emploiDuTemps.creneau')
            ->get()
            ->groupBy('enseignant_id');

        $liste = Enseignant::orderBy('nom')->get()->map(function (Enseignant $enseignant) use ($pointages) {
            $cours = $pointages->get($enseignant->id);

            $dernier = $cours ? $cours->sortByDesc('heure')->first() : null;

            return [
                'enseignant' => $enseignant->only(['id', 'nom', 'prenom', 'specialite', 'email', 'code_pointe']),
                'nb_pointages' => $cours ? $cours->count() : 0,
                'statut' => $dernier ? $dernier->statut : 'absent',
                'heure' => $dernier?->heure,
            ];
        });

        return response()->json($liste);
    }

    public function stats(Request $request): JsonResponse
    {
        $date = $request->query('date', now()->toDateString());

        $total = Enseignant::count();
        $pointes = Pointage::whereDate('date', $date)->distinct('enseignant_id')->count('enseignant_id');
        $retards = Pointage::whereDate('date', $date)->where('statut', 'retard')->distinct('enseignant_id')->count('enseignant_id');

        return response()->json([
            'total' => $total,
            'pointes' => $pointes,
            'retards' => $retards,
            'absents' => $total - $pointes,
        ]);
    }

    private function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000;

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
