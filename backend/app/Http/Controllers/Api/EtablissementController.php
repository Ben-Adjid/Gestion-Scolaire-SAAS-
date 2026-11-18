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
        // Un compte sans établissement (super admin) voit l'identité de la plateforme, jamais celle d'une école.
        $compte = $request->user();
        $etablissement = $compte->role !== 'super_admin' && $compte->etablissement_id
            ? Etablissement::find($compte->etablissement_id)
            : null;

        $donnees = $etablissement ? $etablissement->toArray() : [
            'nom' => 'Plateforme de gestion scolaire',
            'logo_path' => null,
            'couleur_principale' => null,
            'devise' => null,
            'ministere' => null,
            'annee_scolaire' => null,
            'prefixe_matricule' => null,
        ];

        // Le catalogue des formules permet au frontend d'indiquer la formule minimale requise.
        return response()->json($donnees + ['formules' => config('plans.plans')]);
    }

    public function update(EtablissementRequest $request): JsonResponse
    {
        $data = $request->validated();
        unset($data['logo']);

        $tenant = $request->user()->etablissement;
        if (array_key_exists('couleur_principale', $data) && $tenant && ! in_array('personnalisation', $tenant->fonctionnalites(), true)) {
            return response()->json([
                'message' => "La personnalisation de l'espace est réservée à la formule Entreprise.",
                'code' => 'plan_insuffisant',
            ], 403);
        }

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
