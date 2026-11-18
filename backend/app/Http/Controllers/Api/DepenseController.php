<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Depense;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Dépenses de l'établissement (formule Entreprise, module comptabilité avancée). */
class DepenseController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Depense::orderByDesc('date')->orderByDesc('id')->limit(300)->get());
    }

    /** Export Excel de toutes les dépenses, avec une ligne de total. */
    public function export()
    {
        $depenses = Depense::orderByDesc('date')->orderByDesc('id')->get();
        $lignes = $depenses->map(fn ($d) => [
            $d->date?->format('d/m/Y'), $d->categorie, $d->libelle, (float) $d->montant,
        ])->all();
        $lignes[] = ['Total', '', '', round((float) $depenses->sum('montant'), 2)];

        $m = auth()->user()?->etablissement?->monnaie() ?? 'FCFA';
        $contenu = \App\Services\Xlsx::generer('Dépenses', ['Date', 'Catégorie', 'Libellé', "Montant ($m)"], $lignes, ["Montant ($m)"],
            implode(' — ', array_filter([auth()->user()?->etablissement?->nom, 'Dépenses'])));

        return response($contenu, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="depenses.xlsx"',
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'categorie' => ['required', Rule::in(Depense::CATEGORIES)],
            'libelle' => 'required|string|max:150',
            'montant' => 'required|numeric|min:1|max:999999999',
            'date' => 'required|date',
        ]);

        $depense = Depense::create($data + ['etablissement_id' => $request->user()->etablissement_id]);

        AuditLogger::log($request->user(), 'depense', 'depenses', $depense->id, "Dépense « {$data['libelle']} » enregistrée.");

        return response()->json(['message' => 'Dépense enregistrée.', 'depense' => $depense], 201);
    }

    public function destroy(Request $request, Depense $depense): JsonResponse
    {
        $depense->delete();

        AuditLogger::log($request->user(), 'depense', 'depenses', $depense->id, 'Dépense supprimée.');

        return response()->json(['message' => 'Dépense supprimée.']);
    }
}
