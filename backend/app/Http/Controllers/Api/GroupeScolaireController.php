<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Etablissement;
use App\Models\GroupeScolaire;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Multi-sites (formule Entreprise).
 * - La plateforme crée les groupes et y rattache les établissements.
 * - Un administrateur d'un site du groupe consulte la synthèse consolidée : indicateurs agrégés par site.
 *   Les données détaillées (élèves, notes, dossiers) restent cloisonnées par établissement.
 */
class GroupeScolaireController extends Controller
{
    /** Liste des groupes avec leurs sites, et des établissements encore sans groupe (super admin). */
    public function index(): JsonResponse
    {
        $formater = fn ($e) => [
            'id' => $e->id,
            'nom' => $e->nom,
            'plan' => $e->plan ?? 'standard',
            'statut_abonnement' => $e->statut_abonnement ?? 'actif',
        ];

        $groupes = GroupeScolaire::orderBy('nom')->get()->map(fn (GroupeScolaire $groupe) => [
            'id' => $groupe->id,
            'nom' => $groupe->nom,
            'sites' => Etablissement::where('groupe_scolaire_id', $groupe->id)->orderBy('nom')->get()->map($formater)->values(),
        ]);

        $sansGroupe = Etablissement::whereNull('groupe_scolaire_id')->orderBy('nom')->get()->map($formater)->values();

        return response()->json(['groupes' => $groupes->values(), 'sans_groupe' => $sansGroupe]);
    }

    /** Renommer un groupe scolaire. */
    public function update(Request $request, GroupeScolaire $groupe): JsonResponse
    {
        $data = $request->validate(['nom' => 'required|string|max:150']);
        $groupe->update($data);

        AuditLogger::log($request->user(), 'modification_groupe', 'groupes_scolaires', $groupe->id, "Groupe renommé en « {$groupe->nom} ».");

        return response()->json(['message' => 'Groupe renommé.', 'groupe' => $groupe]);
    }

    /** Supprimer un groupe. Impossible tant qu'il contient des sites : on les retire d'abord. */
    public function destroy(Request $request, GroupeScolaire $groupe): JsonResponse
    {
        if ($groupe->etablissements()->exists()) {
            return response()->json([
                'message' => 'Ce groupe contient encore des établissements. Retirez-les du groupe avant de le supprimer.',
            ], 422);
        }

        $nom = $groupe->nom;
        $groupe->delete();

        AuditLogger::log($request->user(), 'suppression_groupe', 'groupes_scolaires', null, "Groupe « {$nom} » supprimé.");

        return response()->json(['message' => 'Groupe supprimé.']);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['nom' => 'required|string|max:150']);
        $groupe = GroupeScolaire::create($data);

        AuditLogger::log($request->user(), 'creation_groupe', 'groupes_scolaires', $groupe->id, "Groupe scolaire « {$groupe->nom} » créé.");

        return response()->json(['message' => 'Groupe créé.', 'groupe' => $groupe], 201);
    }

    public function lier(Request $request, Etablissement $etablissement): JsonResponse
    {
        $data = $request->validate(['groupe_scolaire_id' => 'nullable|integer|exists:groupes_scolaires,id']);
        $etablissement->update(['groupe_scolaire_id' => $data['groupe_scolaire_id'] ?? null]);

        AuditLogger::log($request->user(), 'rattachement_groupe', 'etablissements', $etablissement->id, 'Rattachement de l\'établissement à un groupe.');

        return response()->json(['message' => 'Établissement rattaché au groupe.']);
    }

    public function synthese(Request $request): JsonResponse
    {
        $moi = $request->user()->etablissement;
        abort_unless(in_array('multi_sites', $moi?->fonctionnalites() ?? [], true), 403, 'Le multi-sites est inclus dans la formule Entreprise.');
        // Sans groupe, ce n'est pas une erreur : la page explique simplement comment le rattacher.
        if (! $moi->groupe_scolaire_id) {
            return response()->json([
                'groupe' => null,
                'message' => "Cet établissement n'est rattaché à aucun groupe scolaire. La plateforme peut le rattacher à votre groupe.",
                'sites' => [],
                'totaux' => null,
            ]);
        }

        // Requêtes agrégées sans le filtre par établissement : seuls des totaux par site sont renvoyés.
        $sites = DB::table('etablissements')
            ->where('groupe_scolaire_id', $moi->groupe_scolaire_id)
            ->orderBy('nom')
            ->get(['id', 'nom', 'plan', 'statut_abonnement']);

        $lignes = $sites->map(function ($site) {
            $facture = (float) DB::table('frais_scolaires')->where('etablissement_id', $site->id)->sum('montant');
            $encaisse = (float) DB::table('paiements')->where('etablissement_id', $site->id)->where('statut', 'completed')->sum('montant');

            return [
                'id' => $site->id,
                'nom' => $site->nom,
                'plan' => $site->plan,
                'statut' => $site->statut_abonnement,
                'eleves' => DB::table('etudiants')->where('etablissement_id', $site->id)->count(),
                'facture' => round($facture, 2),
                'encaisse' => round($encaisse, 2),
                'taux_recouvrement' => $facture > 0 ? round($encaisse / $facture * 100, 1) : null,
            ];
        });

        $totalFacture = $lignes->sum('facture');
        $totalEncaisse = $lignes->sum('encaisse');

        return response()->json([
            'groupe' => GroupeScolaire::find($moi->groupe_scolaire_id)?->nom,
            'sites' => $lignes->values(),
            'totaux' => [
                'sites' => $lignes->count(),
                'eleves' => $lignes->sum('eleves'),
                'facture' => round($totalFacture, 2),
                'encaisse' => round($totalEncaisse, 2),
                'taux_recouvrement' => $totalFacture > 0 ? round($totalEncaisse / $totalFacture * 100, 1) : null,
            ],
        ]);
    }
}
