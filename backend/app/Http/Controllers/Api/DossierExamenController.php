<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DossierExamenRequest;
use App\Models\DossierExamen;
use App\Models\Etudiant;
use App\Services\AuditLogger;
use App\Services\TeacherScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DossierExamenController extends Controller
{
    private const NIVEAUX_EXAMEN = ['3ème', 'Terminale'];

    public function __construct()
    {
        $this->middleware('role:admin,censeur,cpe')->only(['store']);
        $this->middleware('role:admin,proviseur,censeur,cpe,secretaire')->only(['index']);
    }

    public function index(Request $request): JsonResponse
    {
        $classeIds = TeacherScope::classeIds($request->user());

        $query = Etudiant::with(['classe:id,nom,niveau', 'dossierExamen'])
            ->whereHas('classe', fn ($q) => $q->whereIn('niveau', self::NIVEAUX_EXAMEN))
            ->when($classeIds !== null, fn ($q) => $q->whereIn('classe_id', $classeIds));

        if ($request->query('classe_id')) {
            $query->where('classe_id', $request->query('classe_id'));
        }

        $etudiants = $query->orderBy('nom')->get();

        $eleves = $etudiants->map(function (Etudiant $etudiant) {
            return [
                'id' => $etudiant->id,
                'matricule' => $etudiant->matricule,
                'nom' => $etudiant->nom,
                'prenom' => $etudiant->prenom,
                'classe' => $etudiant->classe ? $etudiant->classe->only(['id', 'nom', 'niveau']) : null,
                'examen' => $etudiant->classe?->niveau === 'Terminale' ? 'bac' : 'brevet',
                'dossier' => $etudiant->dossierExamen,
            ];
        });

        $complets = $eleves->filter(fn ($e) => $e['dossier']?->complet === true)->count();

        return response()->json([
            'statistiques' => [
                'total' => $eleves->count(),
                'complets' => $complets,
                'incomplets' => $eleves->count() - $complets,
            ],
            'eleves' => $eleves->values(),
        ]);
    }

    public function store(DossierExamenRequest $request, Etudiant $etudiant): JsonResponse
    {
        $data = $request->validated();
        $data['etudiant_id'] = $etudiant->id;

        $uploads = [
            'acte_naissance_file' => ['acte_naissance_path', 'acte_naissance'],
            'photos_identite_file' => ['photos_identite_path', 'photos_identite'],
            'fiche_renseignement_file' => ['fiche_renseignement_path', 'fiche_renseignement'],
        ];

        foreach ($uploads as $field => [$pathField, $boolField]) {
            if ($request->hasFile($field)) {
                $data[$pathField] = $this->storeFile($request->file($field));
                $data[$boolField] = true;
            }
            unset($data[$field]);
        }

        $dossier = DossierExamen::updateOrCreate(
            ['etudiant_id' => $etudiant->id],
            $data
        );

        AuditLogger::log(
            $request->user(),
            'modification',
            'dossiers_examen',
            $dossier->id,
            "Mise à jour du dossier d'examen de l'élève #".$etudiant->id.' ('.$data['examen'].').'
        );

        return response()->json([
            'message' => 'Dossier mis à jour avec succès.',
            'dossier' => $dossier,
        ]);
    }

    private function storeFile($file): string
    {
        return $file->store('dossiers/examen', 'private');
    }
}
