<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Regle;
use Illuminate\Http\Request;
use App\Services\AuditLogger;

class RegleController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('role:admin,proviseur')->except(['index', 'show']);
    }

    public function index()
    {
        $regle = Regle::where('etablissement_id', auth()->user()->etablissement_id)->first();
        return response()->json($regle ?? Regle::factory()->make());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'seuil_admission' => 'required|numeric|min:0|max:20',
            'seuil_bien' => 'required|numeric|min:0|max:20',
            'seuil_tres_bien' => 'required|numeric|min:0|max:20',
            'seuil_excellence' => 'required|numeric|min:0|max:20',
            'nb_tableau_honneur' => 'required|integer|min:1',
        ]);

        $regle = Regle::updateOrCreate(
            ['etablissement_id' => auth()->user()->etablissement_id],
            $validated
        );

        AuditLogger::log(auth()->user(), 'upsert', 'Regle', $regle->id, "Mise à jour des règles de l'établissement");

        return response()->json($regle, 201);
    }

    public function show(Regle $regle)
    {
        return response()->json($regle);
    }

    public function update(Request $request, Regle $regle)
    {
        $validated = $request->validate([
            'seuil_admission' => 'numeric|min:0|max:20',
            'seuil_bien' => 'numeric|min:0|max:20',
            'seuil_tres_bien' => 'numeric|min:0|max:20',
            'seuil_excellence' => 'numeric|min:0|max:20',
            'nb_tableau_honneur' => 'integer|min:1',
        ]);

        $regle->update($validated);

        AuditLogger::log(auth()->user(), 'update', 'Regle', $regle->id, "Mise à jour des règles");

        return response()->json($regle);
    }
}
