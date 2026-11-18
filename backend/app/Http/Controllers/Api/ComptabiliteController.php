<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Comptabilité avancée (formule Entreprise) : frais perçus, impayés, encaissements dans le temps.
 * Toutes les requêtes sont filtrées sur l'établissement de l'utilisateur connecté.
 */
class ComptabiliteController extends Controller
{
    public function synthese(Request $request): JsonResponse
    {
        $tenant = $request->user()->etablissement_id;

        $frais = DB::table('frais_scolaires')
            ->join('etudiants', 'etudiants.id', '=', 'frais_scolaires.etudiant_id')
            ->leftJoin('classes', 'classes.id', '=', 'etudiants.classe_id')
            ->where('frais_scolaires.etablissement_id', $tenant)
            ->get(['frais_scolaires.id', 'frais_scolaires.montant', 'classes.nom as classe']);

        $payesParFrais = DB::table('paiements')
            ->where('etablissement_id', $tenant)
            ->where('statut', 'completed')
            ->groupBy('frais_scolaire_id')
            ->select('frais_scolaire_id', DB::raw('SUM(montant) as paye'))
            ->pluck('paye', 'frais_scolaire_id');

        $facture = 0.0;
        $encaisse = 0.0;
        $parClasse = [];

        foreach ($frais as $ligne) {
            $paye = min((float) $ligne->montant, (float) ($payesParFrais[$ligne->id] ?? 0));
            $facture += (float) $ligne->montant;
            $encaisse += $paye;

            $classe = $ligne->classe ?? 'Sans classe';
            $parClasse[$classe] ??= ['classe' => $classe, 'facture' => 0.0, 'encaisse' => 0.0];
            $parClasse[$classe]['facture'] += (float) $ligne->montant;
            $parClasse[$classe]['encaisse'] += $paye;
        }

        $encaissementsParMois = DB::table('paiements')
            ->where('etablissement_id', $tenant)
            ->where('statut', 'completed')
            ->where('date_paiement', '>=', now()->subMonths(11)->startOfMonth()->toDateString())
            ->pluck('montant', 'date_paiement')
            ->groupBy(fn ($montant, $date) => substr((string) $date, 0, 7))
            ->map(fn ($montants) => (float) $montants->sum())
            ->sortKeys()
            ->map(fn ($total, $mois) => ['mois' => $mois, 'total' => $total])
            ->values();

        $impaye = max(0.0, $facture - $encaisse);

        // Dépenses de l'établissement, et répartition par catégorie (camembert).
        $depensesTotal = (float) DB::table('depenses')->where('etablissement_id', $tenant)->sum('montant');
        $depensesParCategorie = DB::table('depenses')
            ->where('etablissement_id', $tenant)
            ->groupBy('categorie')
            ->select('categorie', DB::raw('SUM(montant) as total'))
            ->get()
            ->map(fn ($l) => ['categorie' => $l->categorie, 'total' => round((float) $l->total, 2)])
            ->values();

        return response()->json($this->donnees($tenant, $facture, $encaisse, $impaye, $depensesTotal, $depensesParCategorie, $encaissementsParMois, $parClasse));
    }

    /** Export Excel : recouvrement par classe, avec une ligne de total. */
    public function export(Request $request)
    {
        $synthese = $this->synthese($request)->getData(true);
        $lignes = [];
        $tot = ['facture' => 0, 'encaisse' => 0, 'impaye' => 0];
        foreach ($synthese['par_classe'] as $c) {
            $lignes[] = [$c['classe'], (float) $c['facture'], (float) $c['encaisse'], (float) $c['impaye']];
            $tot['facture'] += $c['facture'];
            $tot['encaisse'] += $c['encaisse'];
            $tot['impaye'] += $c['impaye'];
        }
        $lignes[] = ['Total', round($tot['facture'], 2), round($tot['encaisse'], 2), round($tot['impaye'], 2)];

        $m = $request->user()->etablissement?->monnaie() ?? 'FCFA';
        $contenu = \App\Services\Xlsx::generer('Recouvrement', ['Classe', "Facturé ($m)", "Encaissé ($m)", "Impayé ($m)"], $lignes,
            ["Facturé ($m)", "Encaissé ($m)", "Impayé ($m)"],
            implode(' — ', array_filter([$request->user()->etablissement?->nom, 'Recouvrement par classe'])));

        return response($contenu, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="comptabilite-recouvrement.xlsx"',
        ]);
    }

    private function donnees($tenant, $facture, $encaisse, $impaye, $depensesTotal, $depensesParCategorie, $encaissementsParMois, $parClasse): array
    {
        return [
            'kpis' => [
                'facture' => round($facture, 2),
                'encaisse' => round($encaisse, 2),
                'impaye' => round($impaye, 2),
                'taux_recouvrement' => $facture > 0 ? round($encaisse / $facture * 100, 1) : null,
            ],
            'depenses' => [
                'total' => round($depensesTotal, 2),
                'resultat_net' => round($encaisse - $depensesTotal, 2),
                'par_categorie' => $depensesParCategorie,
            ],
            'encaissements_par_mois' => $encaissementsParMois,
            'par_classe' => collect($parClasse)->map(fn ($c) => $c + ['impaye' => round(max(0, $c['facture'] - $c['encaisse']), 2)])
                ->sortBy('classe')->values(),
        ];
    }
}
