<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Creneau;
use App\Models\EmploiDuTemps;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreneauController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin,censeur')->except(['index']);
    }

    public function index(): JsonResponse
    {
        return response()->json(Creneau::orderBy('ordre')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $creneau = Creneau::create($this->valider($request));

        AuditLogger::log($request->user(), 'create', 'Creneau', $creneau->id, "Créneau {$creneau->heure_debut}–{$creneau->heure_fin} ajouté");

        return response()->json($creneau, 201);
    }

    public function update(Request $request, Creneau $creneau): JsonResponse
    {
        $creneau->update($this->valider($request, $creneau));

        AuditLogger::log($request->user(), 'update', 'Creneau', $creneau->id, "Créneau modifié");

        return response()->json($creneau);
    }

    public function destroy(Request $request, Creneau $creneau): JsonResponse
    {
        if (EmploiDuTemps::withoutGlobalScope('tenant')->where('creneau_id', $creneau->id)->exists()) {
            return response()->json([
                'message' => 'Ce créneau est utilisé dans l\'emploi du temps. Retirez d\'abord les cours concernés.',
            ], 422);
        }

        $creneau->delete();

        AuditLogger::log($request->user(), 'delete', 'Creneau', $creneau->id, "Créneau supprimé");

        return response()->json(null, 204);
    }

    private function valider(Request $request, ?Creneau $existant = null): array
    {
        $donnees = $request->validate([
            'heure_debut' => 'required|date_format:H:i',
            'heure_fin' => 'required|date_format:H:i|after:heure_debut',
            'ordre' => 'required|integer|min:1',
        ]);

        $doublon = Creneau::where('ordre', $donnees['ordre'])
            ->when($existant, fn ($q) => $q->where('id', '!=', $existant->id))
            ->exists();

        if ($doublon) {
            abort(422, "Un créneau utilise déjà l'ordre {$donnees['ordre']}.");
        }

        return $donnees;
    }
}
