<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AbsenceJustification;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JustificationController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:cpe,admin,censeur,proviseur');
    }

    public function index(Request $request): JsonResponse
    {
        $justifications = AbsenceJustification::with(['absence.etudiant:id,nom,prenom,matricule', 'demandeur:id,name'])
            ->when($request->query('statut'), fn ($q, $v) => $q->where('statut', $v), fn ($q) => $q->where('statut', 'en_attente'))
            ->orderBy('created_at')
            ->get();

        return response()->json($justifications);
    }

    /**
     * Seul le CPE (ou la direction) valide ou refuse ; une demande ne se décide qu'une fois.
     */
    public function decider(Request $request, AbsenceJustification $justification): JsonResponse
    {
        abort_unless($justification->statut === 'en_attente', 422, 'Cette demande a déjà été traitée.');

        $validated = $request->validate([
            'decision' => 'required|in:acceptee,refusee',
            'commentaire' => 'nullable|string|max:1000',
        ]);

        $justification->update([
            'statut' => $validated['decision'],
            'decide_par' => $request->user()->id,
            'decide_le' => now(),
            'commentaire_decision' => $validated['commentaire'] ?? null,
        ]);

        if ($validated['decision'] === 'acceptee') {
            $justification->absence()->update(['justifie' => true, 'motif' => $justification->motif]);
        }

        AuditLogger::log($request->user(), 'decision_justification', 'absences', $justification->absence_id, "Demande {$validated['decision']}");

        return response()->json(['message' => $validated['decision'] === 'acceptee' ? 'Absence justifiée.' : 'Demande refusée.', 'justification' => $justification->fresh()]);
    }
}
