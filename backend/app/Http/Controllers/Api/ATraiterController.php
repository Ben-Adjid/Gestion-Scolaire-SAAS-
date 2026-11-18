<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Absence;
use App\Models\AbsenceJustification;
use App\Models\Appel;
use App\Models\FraisScolaire;
use App\Models\Note;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * « À traiter aujourd'hui » : ce qui attend une décision ou une action de la direction, avec un lien direct.
 * Chaque compteur est limité à l'établissement de l'utilisateur.
 */
class ATraiterController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $etab = (int) $request->user()->etablissement_id;

        $taches = [
            [
                'libelle' => 'Absences non justifiées (7 derniers jours)',
                'nombre' => Absence::whereHas('etudiant', fn ($q) => $q->where('etablissement_id', $etab))
                    ->where('justifie', false)->where('date', '>=', now()->subDays(7)->toDateString())->count(),
                'lien' => '/presences',
            ],
            [
                'libelle' => 'Demandes de justification à décider',
                'nombre' => AbsenceJustification::withoutGlobalScopes()->where('etablissement_id', $etab)
                    ->where('statut', 'en_attente')->count(),
                'lien' => '/justifications',
            ],
            [
                'libelle' => 'Notes en brouillon à valider',
                'nombre' => Note::withoutGlobalScopes()->where('etablissement_id', $etab)
                    ->where('statut', 'brouillon')->count(),
                'lien' => '/notes',
            ],
            [
                'libelle' => 'Appels non validés',
                'nombre' => Appel::whereHas('classe', fn ($q) => $q->where('etablissement_id', $etab))
                    ->where('statut', 'brouillon')->count(),
                'lien' => '/presences',
            ],
            [
                'libelle' => 'Frais scolaires en retard de paiement',
                'nombre' => FraisScolaire::withoutGlobalScopes()->where('etablissement_id', $etab)
                    ->whereIn('statut', ['pending', 'partial', 'overdue'])
                    ->whereDate('date_echeance', '<', now()->toDateString())->count(),
                'lien' => '/frais-scolaires',
            ],
        ];

        $taches = array_values(array_filter($taches, fn ($t) => $t['nombre'] > 0));

        return response()->json([
            'total' => array_sum(array_column($taches, 'nombre')),
            'taches' => $taches,
        ]);
    }
}
