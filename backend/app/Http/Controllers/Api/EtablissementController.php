<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EtablissementRequest;
use App\Models\Etablissement;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class EtablissementController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin')->only(['update']);
    }

    public function show(Request $request): JsonResponse
    {
        $etablissement = $request->user()->etablissement_id
            ? Etablissement::find($request->user()->etablissement_id)
            : Etablissement::first();

        return response()->json(
            $etablissement ?? [
                'nom' => 'Lycée Ibnou Cina',
                'devise' => 'Unité — Travail — Progrès',
                'ministere' => "Ministère de l'Éducation Nationale",
                'annee_scolaire' => '2025-2026',
                'prefixe_matricule' => 'LIC',
            ]
        );
    }

    public function update(EtablissementRequest $request): JsonResponse
    {
        $data = $request->validated();
        unset($data['logo']);

        if ($request->hasFile('logo')) {
            $directory = public_path('uploads/logos');
            File::ensureDirectoryExists($directory);

            $file = $request->file('logo');
            $filename = 'logo_'.uniqid().'.'.$file->getClientOriginalExtension();
            $file->move($directory, $filename);

            $data['logo_path'] = 'uploads/logos/'.$filename;
        }

        if ($request->user()->isSuperAdmin()) {
            return response()->json([
                'message' => "Utilisez la gestion SaaS pour modifier un établissement.",
            ], 422);
        }

        $etablissement = Etablissement::find($request->user()->etablissement_id);

        if ($etablissement) {
            $etablissement->update($data);
        } else {
            $etablissement = Etablissement::create($data);
        }

        AuditLogger::log($request->user(), 'modification', 'etablissement', $etablissement->id, "Modification des informations de l'établissement.");

        return response()->json([
            'message' => "Informations de l'établissement mises à jour avec succès.",
            'etablissement' => $etablissement,
        ]);
    }
}
