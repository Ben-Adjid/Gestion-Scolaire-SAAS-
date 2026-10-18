<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EmploiDuTempsRequest;
use App\Models\Creneau;
use App\Models\EmploiDuTemps;
use App\Services\TeacherScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmploiDuTempsController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin,censeur')->only(['store', 'update', 'destroy']);
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $entries = EmploiDuTemps::query()
            ->with(['matiere:id,nom,couleur', 'enseignant:id,nom,prenom', 'classe:id,nom,niveau', 'creneau:id,heure_debut,heure_fin,ordre'])
            ->when(TeacherScope::isTeacherRole($user), fn ($q) => $q->where('enseignant_id', $user->enseignant_id))
            ->when(! TeacherScope::isTeacherRole($user) && $request->filled('classe_id'), fn ($q) => $q->where('classe_id', $request->query('classe_id')))
            ->when(! TeacherScope::isTeacherRole($user) && $request->filled('enseignant_id'), fn ($q) => $q->where('enseignant_id', $request->query('enseignant_id')))
            ->orderBy('jour')
            ->orderBy('creneau_id')
            ->get();

        return response()->json(['emploi_du_temps' => $entries]);
    }

    public function store(EmploiDuTempsRequest $request): JsonResponse
    {
        $data = $request->validated();

        if ($message = $this->affectationManquante($data) ?? $this->detectConflict($data)) {
            return response()->json(['message' => $message], 422);
        }

        $entry = EmploiDuTemps::create($data);

        return response()->json([
            'message' => 'Cours programmé avec succès.',
            'emploi_du_temps' => $entry->load(['matiere:id,nom,couleur', 'enseignant:id,nom,prenom', 'creneau:id,heure_debut,heure_fin,ordre']),
        ], 201);
    }

    public function update(EmploiDuTempsRequest $request, EmploiDuTemps $emploiDuTemps): JsonResponse
    {
        $data = $request->validated();

        if ($message = $this->affectationManquante($data) ?? $this->detectConflict($data, $emploiDuTemps->id)) {
            return response()->json(['message' => $message], 422);
        }

        $emploiDuTemps->update($data);

        return response()->json([
            'message' => 'Cours mis à jour avec succès.',
            'emploi_du_temps' => $emploiDuTemps->load(['matiere:id,nom,couleur', 'enseignant:id,nom,prenom', 'creneau:id,heure_debut,heure_fin,ordre']),
        ]);
    }

    public function destroy(EmploiDuTemps $emploiDuTemps): JsonResponse
    {
        $emploiDuTemps->delete();

        return response()->json(['message' => 'Cours retiré de l\'emploi du temps.']);
    }

    private function affectationManquante(array $data): ?string
    {
        if (empty($data['enseignant_id'])) {
            return null;
        }

        $classeAttribuee = DB::table('classe_enseignant')
            ->where('enseignant_id', $data['enseignant_id'])
            ->where('classe_id', $data['classe_id'])
            ->exists();

        if (! $classeAttribuee) {
            return "Cet enseignant n'est pas affecté à cette classe.";
        }

        $matiereAttribuee = DB::table('enseignant_matiere')
            ->where('enseignant_id', $data['enseignant_id'])
            ->where('matiere_id', $data['matiere_id'])
            ->exists();

        return $matiereAttribuee ? null : "Cet enseignant n'enseigne pas cette matière.";
    }

    private function detectConflict(array $data, ?int $excludeId = null): ?string
    {
        $base = EmploiDuTemps::query()
            ->where('jour', $data['jour'])
            ->where('creneau_id', $data['creneau_id']);

        if ($excludeId) {
            $base->where('id', '!=', $excludeId);
        }

        // Conflit de classe : une classe ne peut pas avoir deux cours en même temps
        $classeConflict = (clone $base)->where('classe_id', $data['classe_id'])->first();
        if ($classeConflict) {
            return "Conflit : la classe « {$classeConflict->classe->nom} » a déjà un cours à ce créneau.";
        }

        // Conflit d'enseignant : un enseignant ne peut pas être à deux endroits
        if (! empty($data['enseignant_id'])) {
            $enseignantConflict = (clone $base)->where('enseignant_id', $data['enseignant_id'])->first();
            if ($enseignantConflict) {
                return "Conflit : l'enseignant « {$enseignantConflict->enseignant->prenom} {$enseignantConflict->enseignant->nom} » est déjà occupé à ce créneau.";
            }
        }

        // Conflit de salle : une salle ne peut pas accueillir deux cours
        if (! empty($data['salle'])) {
            $salleConflict = (clone $base)->where('salle', $data['salle'])->first();
            if ($salleConflict) {
                return "Conflit : la salle « {$data['salle']} » est déjà occupée à ce créneau.";
            }
        }

        return null;
    }
}
