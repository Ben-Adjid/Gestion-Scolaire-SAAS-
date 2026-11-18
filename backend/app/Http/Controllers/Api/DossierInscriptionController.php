<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DossierInscriptionRequest;
use App\Models\DossierInscription;
use App\Models\Etudiant;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DossierInscriptionController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin,secretaire')->only(['update']);
    }

    public function show(Etudiant $etudiant): JsonResponse
    {
        $this->authorize('view', $etudiant);

        return response()->json($etudiant->dossierInscription);
    }

    public function fichier(Etudiant $etudiant, string $type): StreamedResponse
    {
        $this->authorize('view', $etudiant);

        $chemins = [
            'acte_naissance' => $etudiant->dossierInscription?->acte_naissance_path,
            'bulletin' => $etudiant->dossierInscription?->bulletin_path,
            'photo' => $etudiant->dossierInscription?->photo_path,
        ];
        abort_unless(array_key_exists($type, $chemins) && $chemins[$type], 404);
        abort_unless(Storage::disk('private')->exists($chemins[$type]), 404);

        return Storage::disk('private')->download($chemins[$type], $type.'.'.pathinfo($chemins[$type], PATHINFO_EXTENSION));
    }

    public function update(DossierInscriptionRequest $request, Etudiant $etudiant): JsonResponse
    {
        $data = $request->validated();
        $data['etudiant_id'] = $etudiant->id;

        $uploads = [
            'acte_naissance_file' => ['acte_naissance_path', 'acte_naissance'],
            'bulletin_file' => ['bulletin_path', 'bulletin'],
            'photo_file' => ['photo_path', 'photo'],
        ];

        foreach ($uploads as $field => [$pathField, $boolField]) {
            if ($request->hasFile($field)) {
                $data[$pathField] = $this->storeFile($request->file($field));
                $data[$boolField] = true;
            }
            unset($data[$field]);
        }

        $dossier = DossierInscription::updateOrCreate(
            ['etudiant_id' => $etudiant->id],
            $data
        );

        AuditLogger::log(
            $request->user(),
            'modification',
            'dossiers_inscription',
            $dossier->id,
            "Mise à jour du dossier d'inscription de l'élève #".$etudiant->id.'.'
        );

        return response()->json([
            'message' => "Dossier d'inscription enregistré avec succès.",
            'dossier' => $dossier,
        ]);
    }

    private function storeFile($file): string
    {
        return $file->store('dossiers/inscription', 'private');
    }
}
