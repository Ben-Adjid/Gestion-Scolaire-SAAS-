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
        $etudiant->load('classe:id,nom,niveau');

        $absences = Absence::where('etudiant_id', $etudiant->id)->orderByDesc('date')->get();

        $bulletinsPublies = [];
        foreach ([1, 2, 3] as $trimestre) {
            $pvPublie = ConseilClasse::where('classe_id', $etudiant->classe_id)->where('trimestre', $trimestre)->exists();
            if ($pvPublie) {
                $bulletinsPublies[] = $bulletins->generateBulletin($etudiant, $trimestre);
            }
        }

        $frais = \App\Models\FraisScolaire::where('etudiant_id', $etudiant->id)->with('paiements')->get();

        return response()->json([
            'eleve' => $etudiant->only(['id', 'nom', 'prenom', 'matricule']),
            'classe' => $etudiant->classe?->only(['nom', 'niveau']),
            'presences' => [
                'absences' => $absences->where('type', 'absence')->count(),
                'retards' => $absences->where('type', 'retard')->count(),
                'liste' => $absences->map(fn ($a) => [
                    'id' => $a->id, 'date' => $a->date?->toDateString(), 'type' => $a->type,
                    'justifie' => (bool) $a->justifie, 'motif' => $a->motif,
                ])->values(),
            ],
            'convocations' => \App\Models\Convocation::where('etudiant_id', $etudiant->id)->orderByDesc('date')
                ->get(['date', 'motif']),
            'notes' => \App\Models\Note::where('etudiant_id', $etudiant->id)->where('statut', 'valide')
                ->with('matiere:id,nom')->orderBy('trimestre')->get()
                ->map(fn ($n) => ['matiere' => $n->matiere?->nom, 'trimestre' => $n->trimestre, 'type' => $n->type, 'valeur' => $n->valeur]),
            'bulletins' => $bulletinsPublies,
            'examens' => \App\Models\ExamenPlanning::where('classe_id', $etudiant->classe_id)
                ->whereDate('date', '>=', today())
                ->with('matiere:id,nom')
                ->orderBy('date')->orderBy('heure_debut')
                ->get()
                ->map(fn ($e) => [
                    'date' => $e->date?->toDateString(),
                    'heure' => substr((string) $e->heure_debut, 0, 5),
                    'duree_minutes' => $e->duree_minutes,
                    'salle' => $e->salle,
                    'matiere' => $e->matiere?->nom,
                ]),
            'frais' => $frais->map(fn ($f) => [
                'libelle' => $f->libelle, 'annee_scolaire' => $f->annee_scolaire,
                'montant' => $f->montant_total, 'paye' => $f->montant_paye, 'reste' => $f->reste,
            ]),
        ]);
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
