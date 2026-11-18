<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Analyses avancées (formules VIP et Entreprise). Toutes les requêtes sont filtrées
 * sur l'établissement de l'utilisateur connecté.
 */
class AnalyseController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin,proviseur,censeur');
    }

    public function index(Request $request): JsonResponse
    {
        $tenant = $request->user()->etablissement_id;

        $moyennesParClasse = DB::table('notes')
            ->join('etudiants', 'etudiants.id', '=', 'notes.etudiant_id')
            ->join('classes', 'classes.id', '=', 'etudiants.classe_id')
            ->where('etudiants.etablissement_id', $tenant)
            ->groupBy('classes.id', 'classes.nom')
            ->orderBy('classes.nom')
            ->select('classes.nom', DB::raw('ROUND(AVG(notes.valeur), 2) as moyenne'), DB::raw('COUNT(notes.id) as nb_notes'))
            ->get();

        $absencesParClasse = DB::table('absences')
            ->join('etudiants', 'etudiants.id', '=', 'absences.etudiant_id')
            ->join('classes', 'classes.id', '=', 'etudiants.classe_id')
            ->where('etudiants.etablissement_id', $tenant)
            ->groupBy('classes.id', 'classes.nom')
            ->orderBy('classes.nom')
            ->select(
                'classes.nom',
                DB::raw('COUNT(absences.id) as total'),
                DB::raw('SUM(CASE WHEN absences.justifie = 0 THEN 1 ELSE 0 END) as non_justifiees')
            )
            ->get();

        $absencesParMois = DB::table('absences')
            ->join('etudiants', 'etudiants.id', '=', 'absences.etudiant_id')
            ->where('etudiants.etablissement_id', $tenant)
            ->where('absences.date', '>=', now()->subMonths(5)->startOfMonth()->toDateString())
            ->pluck('absences.date')
            ->groupBy(fn ($date) => substr((string) $date, 0, 7))
            ->map(fn ($dates) => $dates->count())
            ->sortKeys()
            ->map(fn ($total, $mois) => ['mois' => $mois, 'total' => $total])
            ->values();

        $moyennesParTrimestre = DB::table('notes')
            ->join('etudiants', 'etudiants.id', '=', 'notes.etudiant_id')
            ->where('etudiants.etablissement_id', $tenant)
            ->groupBy('notes.trimestre')
            ->orderBy('notes.trimestre')
            ->select('notes.trimestre', DB::raw('ROUND(AVG(notes.valeur), 2) as moyenne'))
            ->get();

        $fraisTotal = (float) DB::table('frais_scolaires')->where('etablissement_id', $tenant)->sum('montant');
        $fraisPaye = (float) DB::table('paiements')
            ->where('etablissement_id', $tenant)
            ->where('statut', 'completed')
            ->sum('montant');

        return response()->json([
            'indicateurs' => [
                'eleves' => DB::table('etudiants')->where('etablissement_id', $tenant)->count(),
                'moyenne_generale' => $moyennesParClasse->isEmpty() ? null : round($moyennesParClasse->avg('moyenne'), 2),
                'taux_recouvrement' => $fraisTotal > 0 ? round($fraisPaye / $fraisTotal * 100, 1) : null,
                'absences_total' => $absencesParClasse->sum('total'),
            ],
            'moyennes_par_classe' => $moyennesParClasse,
            // Moyenne de chaque classe pour chaque trimestre : alimente le graphique interactif.
            'moyennes_classe_trimestre' => DB::table('notes')
                ->join('etudiants', 'etudiants.id', '=', 'notes.etudiant_id')
                ->join('classes', 'classes.id', '=', 'etudiants.classe_id')
                ->where('etudiants.etablissement_id', $tenant)
                ->groupBy('classes.id', 'classes.nom', 'notes.trimestre')
                ->orderBy('classes.nom')->orderBy('notes.trimestre')
                ->select('classes.nom as classe', 'notes.trimestre', DB::raw('ROUND(AVG(notes.valeur), 2) as moyenne'), DB::raw('COUNT(notes.id) as nb_notes'))
                ->get(),
            'absences_par_classe' => $absencesParClasse,
            'absences_par_mois' => $absencesParMois,
            'moyennes_par_trimestre' => $moyennesParTrimestre,
        ]);
    }
}
