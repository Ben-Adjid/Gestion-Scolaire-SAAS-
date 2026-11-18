<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Etudiant;
use App\Models\Regle;
use App\Services\TeacherScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SuiviController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin,proviseur,censeur,cpe');
    }

    public function enDifficulte(Request $request): JsonResponse
    {
        $user = $request->user();
        $regle = Regle::where('etablissement_id', $user->etablissement_id)->first();
        $seuil = $regle?->seuil_admission ?? 10;

        $trimestre = $request->boolean('annuel')
            ? null
            : (int) $request->query('trimestre', 1);

        $classeIds = TeacherScope::classeIds($user);

        $etudiants = Etudiant::with('classe:id,nom,niveau')
            ->when($classeIds !== null, fn ($q) => $q->whereIn('classe_id', $classeIds))
            ->get();

        $averages = DB::table('notes')
            ->select('etudiant_id', DB::raw('AVG(valeur) as moyenne'))
            ->when($trimestre, fn ($q) => $q->where('trimestre', $trimestre))
            ->where('etablissement_id', $user->etablissement_id)
            ->whereIn('etudiant_id', $etudiants->pluck('id')->toArray())
            ->groupBy('etudiant_id')
            ->pluck('moyenne', 'etudiant_id');

        $eleves = [];

        foreach ($averages as $id => $moyenne) {
            if ($moyenne >= $seuil) {
                continue;
            }

            $etudiant = $etudiants->firstWhere('id', $id);

            if (! $etudiant) {
                continue;
            }

            $eleves[] = [
                'id' => $etudiant->id,
                'matricule' => $etudiant->matricule,
                'nom' => $etudiant->nom,
                'prenom' => $etudiant->prenom,
                'classe' => $etudiant->classe ? $etudiant->classe->only(['id', 'nom', 'niveau']) : null,
                'moyenne' => $moyenne,
            ];
        }

        usort($eleves, fn ($a, $b) => $a['moyenne'] <=> $b['moyenne']);

        return response()->json([
            'seuil' => $seuil,
            'eleves' => $eleves,
        ]);
    }

    public function fiche(Request $request, Etudiant $etudiant): JsonResponse
    {
        $etudiant->load('classe:id,nom,niveau');
        $notes = $etudiant->notes()
            ->with('matiere:id,nom')
            ->orderBy('trimestre')
            ->orderBy('created_at')
            ->get()
            ->map(fn ($note) => [
                'matiere' => $note->matiere?->nom,
                'trimestre' => $note->trimestre,
                'type' => $note->type,
                'valeur' => $note->valeur,
                'appreciation' => $note->appreciation,
                'statut' => $note->statut,
            ]);
        $absences = $etudiant->absences()->orderByDesc('date')->get();
        return response()->json([
            'eleve' => $etudiant->only(['id', 'matricule', 'nom', 'prenom']),
            'classe' => $etudiant->classe?->only(['id', 'nom', 'niveau']),
            'synthese' => [
                'notes' => $notes->count(),
                'moyenne' => $notes->isEmpty() ? null : round($notes->avg('valeur'), 2),
                'absences' => $absences->where('type', 'absence')->count(),
                'retards' => $absences->where('type', 'retard')->count(),
            ],
            'notes' => $notes,
            'absences' => $absences->map(fn ($absence) => [
                'date' => $absence->date?->toDateString(),
                'type' => $absence->type,
                'justifie' => (bool) $absence->justifie,
                'motif' => $absence->motif,
            ])->values(),
        ]);
    }
}
