<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Classe;
use App\Models\ConseilClasse;
use App\Models\Etablissement;
use App\Services\AuditLogger;
use App\Services\BulletinService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConseilClasseController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin,proviseur,censeur');
    }

    public function prepare(Classe $classe, Request $request, BulletinService $bulletins): JsonResponse
    {
        $trimestre = (int) $request->query('trimestre', 1);

        return response()->json([
            'classe' => $classe->only(['id', 'nom', 'niveau']),
            'trimestre' => $trimestre,
            'eleves' => $this->synthese($classe, $trimestre, $bulletins),
        ]);
    }

    public function store(Request $request, Classe $classe, BulletinService $bulletins): JsonResponse
    {
        $validated = $request->validate([
            'trimestre' => 'required|in:1,2,3',
            'date_conseil' => 'required|date',
            'observations' => 'nullable|string|max:2000',
        ]);

        $eleves = $this->synthese($classe, (int) $validated['trimestre'], $bulletins);
        $etablissement = Etablissement::first();

        $conseil = ConseilClasse::create([
            'classe_id' => $classe->id,
            'trimestre' => $validated['trimestre'],
            'annee_scolaire' => $etablissement?->annee_scolaire,
            'date_conseil' => $validated['date_conseil'],
            'pv' => $this->formatPV($classe, (int) $validated['trimestre'], $validated['date_conseil'], $eleves, $validated['observations'] ?? null),
            'participants' => [
                'proviseur' => $request->user()->name,
                'enseignants' => $classe->enseignants()->count(),
            ],
            'etablissement_id' => $classe->etablissement_id,
        ]);

        AuditLogger::log($request->user(), 'create', 'ConseilClasse', $conseil->id,
            "Procès-verbal du conseil de classe de {$classe->nom} (trimestre {$validated['trimestre']})");

        return response()->json(['message' => 'Procès-verbal enregistré.', 'conseil' => $conseil], 201);
    }

    public function index(Classe $classe): JsonResponse
    {
        return response()->json(
            ConseilClasse::where('classe_id', $classe->id)->orderByDesc('date_conseil')->get(['id', 'trimestre', 'date_conseil', 'annee_scolaire'])
        );
    }

    public function show(Classe $classe, ConseilClasse $conseil): JsonResponse
    {
        abort_unless($conseil->classe_id === $classe->id, 404);

        return response()->json($conseil);
    }

    private function synthese(Classe $classe, int $trimestre, BulletinService $bulletins): array
    {
        return $classe->etudiants()->orderBy('nom')->get()->map(function ($etudiant) use ($trimestre, $bulletins) {
            $bulletin = $bulletins->generateBulletin($etudiant, $trimestre);

            return [
                'id' => $etudiant->id,
                'nom' => $etudiant->nom,
                'prenom' => $etudiant->prenom,
                'moyenne' => $bulletin['moyenne_generale'],
                'rang' => $bulletin['rang'],
                'mention' => $bulletin['mention']['label'] ?? null,
                'decision' => $bulletin['decision']['label'],
            ];
        })->all();
    }

    private function formatPV(Classe $classe, int $trimestre, string $date, array $eleves, ?string $observations): string
    {
        $pv = "PROCÈS-VERBAL DU CONSEIL DE CLASSE\n\n";
        $pv .= "Classe : {$classe->nom}\nTrimestre : {$trimestre}\nDate : {$date}\n\n";
        $pv .= "DÉCISIONS :\n";

        foreach ($eleves as $eleve) {
            $moyenne = $eleve['moyenne'] !== null ? number_format((float) $eleve['moyenne'], 2, ',', ' ') : '—';
            $pv .= "- {$eleve['prenom']} {$eleve['nom']} : moyenne {$moyenne}/20, {$eleve['decision']}"
                .($eleve['mention'] ? " ({$eleve['mention']})" : '')."\n";
        }

        if ($observations) {
            $pv .= "\nOBSERVATIONS :\n{$observations}\n";
        }

        return $pv;
    }
}
