<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AbsenceRequest;
use App\Models\Absence;
use App\Models\Etudiant;
use App\Services\AuditLogger;
use App\Services\NotificationService;
use App\Services\TeacherScope;
use App\Services\AlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AbsenceController extends Controller
{
    private $notifications;
    private $alerts;

    public function __construct(NotificationService $notifications, AlertService $alerts)
    {
        $this->notifications = $notifications;
        $this->alerts = $alerts;
        $this->middleware('role:enseignant,cpe,admin,censeur')->only(['store']);
        $this->middleware('role:admin,proviseur,censeur,cpe,enseignant,secretaire')->only(['index', 'stats']);
        $this->middleware('role:admin,censeur,cpe')->only(['update', 'destroy']);
    }

    public function show(Absence $absence): JsonResponse
    {
        $this->authorize('view', $absence);

        return response()->json($absence->load('etudiant:id,nom,prenom,matricule'));
    }

    public function update(AbsenceRequest $request, Absence $absence): JsonResponse
    {
        $this->authorize('view', $absence);

        $absence->update($request->validated());

        AuditLogger::log($request->user(), 'modification', 'absences', $absence->id, "Mise à jour d'une absence");

        return response()->json([
            'message' => 'Absence mise à jour.',
            'absence' => $absence->load('etudiant:id,nom,prenom,matricule'),
        ]);
    }

    public function destroy(Request $request, Absence $absence): JsonResponse
    {
        $this->authorize('view', $absence);

        $absence->delete();

        AuditLogger::log($request->user(), 'suppression', 'absences', $absence->id, "Suppression d'une absence");

        return response()->json(['message' => 'Absence supprimée.']);
    }

    public function index(Request $request): JsonResponse
    {
        $query = Absence::with(['etudiant'])
            ->orderByDesc('created_at');

        $classeIds = TeacherScope::classeIds($request->user());
        if ($classeIds !== null) {
            $query->whereHas('etudiant', fn ($q) => $q->whereIn('classe_id', $classeIds));
        }

        if ($request->query('etudiant_id')) {
            $query->where('etudiant_id', $request->query('etudiant_id'));
        }

        if ($request->query('classe_id')) {
            $query->whereHas('etudiant', fn ($q) => 
                $q->where('classe_id', $request->query('classe_id'))
            );
        }

        $absences = $query->paginate((int) $request->query('per_page', 30));

        return response()->json($absences);
    }

    public function store(AbsenceRequest $request): JsonResponse
    {
        $classeEleve = (int) Etudiant::whereKey($request->validated('etudiant_id'))->value('classe_id');
        if (! TeacherScope::canAccessClasse($request->user(), $classeEleve)) {
            abort(403, 'Vous ne pouvez saisir des absences que pour les élèves de vos classes.');
        }

        $absence = Absence::create($request->validated());
        $student = $absence->etudiant;

        // Envoyer notification aux parents
        $this->notifications->notifyAbsence($student, $absence);

        $this->alerts->checkAbsences($student);

        // Vérifier les alertes
        $absenceCount = Absence::where('etudiant_id', $student->id)
            ->where('created_at', '>=', now()->subMonth())
            ->count();

        if ($absenceCount >= 3) {
            $this->notifications->alertTeachers($student, "Absences récurrentes ($absenceCount en 1 mois)");
        }

        if ($absenceCount >= 5) {
            $this->notifications->notifyCPE($student, "Escalade: élève au seuil d'alerte ($absenceCount absences)");
        }

        AuditLogger::log($request->user(), 'création', 'absences', $absence->id,
            "Enregistrement d'une absence pour étudiant {$student->id}");

        return response()->json([
            'message' => 'Absence enregistrée avec succès.',
            'absence' => $absence->load('etudiant'),
        ], 201);
    }

    public function stats(Request $request): JsonResponse
    {
        $classeId = $request->query('classe_id');
        
        $stats = Absence::whereHas('etudiant', fn ($q) => 
            $q->where('classe_id', $classeId)
        )
        ->selectRaw('COUNT(*) as total, AVG(CASE WHEN justifie = 1 THEN 1 ELSE 0 END) as pct_justified')
        ->first();

        return response()->json($stats);
    }
}
