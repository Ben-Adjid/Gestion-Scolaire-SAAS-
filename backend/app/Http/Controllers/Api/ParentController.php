<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Absence;
use App\Models\AbsenceJustification;
use App\Models\ConseilClasse;
use App\Models\Etudiant;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\BulletinService;
use App\Services\AuditLogger as Journal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParentController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:parent')->only(['enfants', 'resume', 'justifier', 'mesJustifications']);
        $this->middleware('role:admin,secretaire')->only(['lier', 'delier']);
    }

    public function enfants(Request $request): JsonResponse
    {
        $parent = $request->user();

        $enfants = $parent->enfants()
            ->where('etudiants.etablissement_id', $parent->etablissement_id)
            ->with('classe:id,nom,niveau')
            ->get(['etudiants.id', 'etudiants.nom', 'etudiants.prenom', 'etudiants.matricule', 'etudiants.classe_id']);

        return response()->json(['enfants' => $enfants]);
    }

    /**
     * Tout ce qui concerne un enfant : présences, convocations, notes validées, bulletins publiés, frais.
     */
    public function resume(Request $request, Etudiant $etudiant, BulletinService $bulletins): JsonResponse
    {
        $this->assertEnfant($request->user(), $etudiant);
        return response()->json(app(\App\Services\ResumeEleve::class)->pour($etudiant));
    }

    /**
     * Le parent demande la justification d'une absence ; c'est le CPE qui valide.
     */
    public function justifier(Request $request, Etudiant $etudiant, Absence $absence): JsonResponse
    {
        $parent = $request->user();
        $this->assertEnfant($parent, $etudiant);

        abort_unless($absence->etudiant_id === $etudiant->id, 404);
        abort_if($absence->justifie, 422, 'Cette absence est déjà justifiée.');

        $validated = $request->validate(['motif' => 'required|string|max:1000']);

        $demande = AbsenceJustification::updateOrCreate(
            ['absence_id' => $absence->id, 'statut' => 'en_attente'],
            [
                'demandeur_id' => $parent->id,
                'motif' => $validated['motif'],
                'etablissement_id' => $parent->etablissement_id,
            ]
        );

        AuditLogger::log($parent, 'demande_justification', 'absences', $absence->id, "Demande de justification pour {$etudiant->prenom} {$etudiant->nom}");

        return response()->json(['message' => 'Demande envoyée au CPE.', 'demande' => $demande], 201);
    }

    public function mesJustifications(Request $request): JsonResponse
    {
        return response()->json(
            AbsenceJustification::where('demandeur_id', $request->user()->id)->with('absence:id,etudiant_id,date,type')->orderByDesc('id')->get()
        );
    }

    public function lier(Request $request, Etudiant $etudiant, User $parent): JsonResponse
    {
        abort_unless($parent->role === 'parent', 422, "Ce compte n'est pas un compte parent.");
        abort_unless($parent->etablissement_id === $etudiant->etablissement_id, 403, "Ce parent n'appartient pas à l'établissement de l'élève.");

        $parent->enfants()->syncWithoutDetaching([$etudiant->id => ['lien' => $request->input('lien', 'parent')]]);

        AuditLogger::log($request->user(), 'lien_parent', 'Etudiant', $etudiant->id, "Lien établi avec le parent {$parent->name}");

        return response()->json(['message' => 'Lien enregistré.']);
    }

    public function delier(Request $request, Etudiant $etudiant, User $parent): JsonResponse
    {
        $parent->enfants()->detach($etudiant->id);

        AuditLogger::log($request->user(), 'delien_parent', 'Etudiant', $etudiant->id, "Lien retiré avec le parent {$parent->name}");

        return response()->json(['message' => 'Lien retiré.']);
    }

    private function assertEnfant(User $parent, Etudiant $etudiant): void
    {
        $lie = $parent->enfants()->where('etudiants.id', $etudiant->id)
            ->where('etudiants.etablissement_id', $parent->etablissement_id)
            ->exists();

        abort_unless($lie, 403, "Vous ne pouvez consulter que les informations de vos enfants.");
    }
}
