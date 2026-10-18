<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\NoteRequest;
use App\Models\Etudiant;
use App\Models\Note;
use App\Services\AuditLogger;
use App\Services\BulletinService;
use App\Services\TeacherScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NoteController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin,proviseur,censeur,enseignant')->only(['index']);
        $this->middleware('role:enseignant')->only(['store']);
        $this->middleware('role:enseignant,proviseur')->only(['update', 'storeBulk']);
        $this->middleware('role:admin,proviseur')->only(['destroy']);
    }

    public function index(Request $request): JsonResponse
    {
        $query = Note::with(['etudiant', 'matiere'])
            ->orderByDesc('created_at');

        $user = $request->user();
        $matiereIds = TeacherScope::matiereIds($user);
        $classeIds = TeacherScope::classeIds($user);
        if ($matiereIds !== null) {
            $query->whereIn('matiere_id', $matiereIds)
                ->whereHas('etudiant', fn ($q) => $q->whereIn('classe_id', $classeIds));
        }

        if ($request->query('etudiant_id')) {
            $query->where('etudiant_id', $request->query('etudiant_id'));
        }

        if ($request->query('matiere_id')) {
            $query->where('matiere_id', $request->query('matiere_id'));
        }

        if ($request->query('trimestre')) {
            $query->where('trimestre', $request->query('trimestre'));
        }

        $notes = $query->paginate((int) $request->query('per_page', 50));

        return response()->json($notes);
    }

    public function store(NoteRequest $request): JsonResponse
    {
        $this->assertTeacherCanGrade($request->user(), $request->validated('etudiant_id'), $request->validated('matiere_id'));

        $existante = Note::where([
            'etudiant_id' => $request->validated('etudiant_id'),
            'matiere_id' => $request->validated('matiere_id'),
            'trimestre' => $request->validated('trimestre'),
        ])->first();

        if ($existante) {
            $this->authorize('update', $existante);
        }

        $note = Note::updateOrCreate(
            [
                'etudiant_id' => $request->validated('etudiant_id'),
                'matiere_id' => $request->validated('matiere_id'),
                'trimestre' => $request->validated('trimestre'),
            ],
            $this->avecAppreciation($request->validated())
        );

        AuditLogger::log($request->user(), 'création', 'notes', $note->id,
            "Saisie de note pour étudiant {$note->etudiant_id}");

        return response()->json([
            'message' => 'Note enregistrée avec succès.',
            'note' => $note->load(['etudiant', 'matiere']),
        ], 201);
    }

    public function show(Note $note): JsonResponse
    {
        $this->authorize('view', $note);

        return response()->json($note->load(['etudiant', 'matiere']));
    }

    public function update(NoteRequest $request, Note $note): JsonResponse
    {
        $this->authorize('update', $note);
        $this->assertTeacherCanGrade($request->user(), $request->validated('etudiant_id'), $request->validated('matiere_id'));

        $note->update($this->avecAppreciation($request->validated()));

        AuditLogger::log($request->user(), 'modification', 'notes', $note->id,
            "Modification de note pour étudiant {$note->etudiant_id}");

        return response()->json([
            'message' => 'Note mise à jour avec succès.',
            'note' => $note->load(['etudiant', 'matiere']),
        ]);
    }

    public function storeBulk(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'matiere_id' => 'required|exists:matieres,id',
            'trimestre' => 'required|in:1,2,3',
            'type' => 'nullable|in:devoir,composition,examen',
            'valider' => 'nullable|boolean',
            'notes' => 'required|array|min:1',
            'notes.*.etudiant_id' => 'required|exists:etudiants,id',
            'notes.*.valeur' => 'required|numeric|min:0|max:20',
        ]);

        $statut = ! empty($validated['valider']) ? 'valide' : 'brouillon';
        $created = 0;

        foreach ($validated['notes'] as $ligne) {
            $this->assertTeacherCanGrade($request->user(), (int) $ligne['etudiant_id'], (int) $validated['matiere_id']);

            $note = Note::firstOrNew([
                'etudiant_id' => $ligne['etudiant_id'],
                'matiere_id' => $validated['matiere_id'],
                'trimestre' => $validated['trimestre'],
            ]);

            if ($note->exists) {
                $this->authorize('update', $note);
            }

            $note->fill([
                'valeur' => $ligne['valeur'],
                'type' => $validated['type'] ?? $note->type ?? 'devoir',
                'statut' => $statut,
                'appreciation' => $this->appreciationPour((float) $ligne['valeur'], $note->appreciation),
            ])->save();
            $created++;
        }

        AuditLogger::log($request->user(), 'création_groupée', 'notes', null,
            "Saisie groupée de $created notes");

        return response()->json([
            'message' => "$created notes enregistrées avec succès.",
            'count' => $created,
        ]);
    }

    private function avecAppreciation(array $data): array
    {
        $data['appreciation'] = $this->appreciationPour((float) $data['valeur'], $data['appreciation'] ?? null);

        return $data;
    }

    private function appreciationPour(float $valeur, ?string $saisie): string
    {
        return filled($saisie) ? $saisie : app(BulletinService::class)->appreciationNote($valeur);
    }

    private function assertTeacherCanGrade($user, int $etudiantId, int $matiereId): void
    {
        $matieres = TeacherScope::matiereIds($user);
        $classes = TeacherScope::classeIds($user);

        if ($matieres === null) {
            return;
        }

        $classeId = Etudiant::whereKey($etudiantId)->value('classe_id');

        if (! in_array($matiereId, $matieres, true) || ! in_array($classeId, $classes, true)) {
            abort(403, "Vous ne pouvez saisir des notes que pour vos matières et vos classes.");
        }
    }

    public function destroy(Note $note): JsonResponse
    {
        $this->authorize('delete', $note);

        $note->delete();

        AuditLogger::log(auth()->user(), 'suppression', 'notes', $note->id,
            "Suppression d'une note");

        return response()->json(['message' => 'Note supprimée avec succès.']);
    }
}
