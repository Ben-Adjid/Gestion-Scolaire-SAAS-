<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AbsenceController;
use App\Http\Controllers\Api\AbsenceEnseignantController;
use App\Http\Controllers\Api\AppelController;
use App\Http\Controllers\Api\AssistantController;
use App\Http\Controllers\Api\AuditController;
use App\Http\Controllers\Api\BulletinController;
use App\Http\Controllers\Api\ClasseController;
use App\Http\Controllers\Api\ConseilClasseController;
use App\Http\Controllers\Api\CreneauController;
use App\Http\Controllers\Api\ConvocationController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DossierExamenController;
use App\Http\Controllers\Api\DossierInscriptionController;
use App\Http\Controllers\Api\EmploiDuTempsController;
use App\Http\Controllers\Api\EnseignantController;
use App\Http\Controllers\Api\EtablissementController;
use App\Http\Controllers\Api\EtudiantController;
use App\Http\Controllers\Api\ExamenController;
use App\Http\Controllers\Api\FraisScolaireController;
use App\Http\Controllers\Api\InscriptionController;
use App\Http\Controllers\Api\MatiereController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\NoteController;
use App\Http\Controllers\Api\PalmaresController;
use App\Http\Controllers\Api\PointageController;
use App\Http\Controllers\Api\RegleController;
use App\Http\Controllers\Api\SuiviController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\SuperAdminController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Toutes les routes sont servies sous le préfixe /api.
|
*/

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1')
    ->withoutMiddleware(['auth:sanctum', 'subscription']);

Route::middleware(['auth:sanctum', 'subscription', 'enforce_tenant'])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/palmares', [PalmaresController::class, 'index']);
    Route::post('/assistant', [AssistantController::class, 'chat']);
    Route::get('/regles', [RegleController::class, 'show']);
    Route::put('/regles', [RegleController::class, 'update']);
    Route::get('/etablissement', [EtablissementController::class, 'show']);
    Route::post('/etablissement', [EtablissementController::class, 'update']);

    Route::get('/absences/stats', [AbsenceController::class, 'stats']);
    Route::apiResource('absences', AbsenceController::class);

    Route::get('/absences-enseignants/stats', [AbsenceEnseignantController::class, 'stats']);
    Route::get('/absences-enseignants', [AbsenceEnseignantController::class, 'index']);
    Route::post('/absences-enseignants', [AbsenceEnseignantController::class, 'store']);
    Route::delete('/absences-enseignants/{absenceEnseignant}', [AbsenceEnseignantController::class, 'destroy']);
    Route::get('/appels/en-attente', [AppelController::class, 'enAttente']);
    Route::get('/appels', [AppelController::class, 'index']);
    Route::post('/appels', [AppelController::class, 'store']);
    Route::post('/appels/{appel}/confirmer', [AppelController::class, 'confirmer']);

    Route::get('/convocations', [ConvocationController::class, 'index']);
    Route::post('/convocations', [ConvocationController::class, 'store']);
    Route::delete('/convocations/{convocation}', [ConvocationController::class, 'destroy']);

    Route::get('/pointages/stats', [PointageController::class, 'stats']);
    Route::get('/pointages/aujourdhui', [PointageController::class, 'aujourdhui']);
    Route::get('/pointages', [PointageController::class, 'index']);
    Route::post('/pointages', [PointageController::class, 'store']);

    Route::get('/messages/stats', [MessageController::class, 'stats']);
    Route::get('/messages', [MessageController::class, 'index']);
    Route::post('/messages', [MessageController::class, 'store']);
    Route::post('/messages/{message}/lu', [MessageController::class, 'markAsRead']);
    Route::get('/messages/{message}/fichier', [MessageController::class, 'fichier']);
    Route::get('/messages/inbox', [MessageController::class, 'inbox']);
    Route::get('/messages/sent', [MessageController::class, 'sent']);

    Route::get('/frais-scolaires/stats', [FraisScolaireController::class, 'stats']);
    Route::post('/frais-scolaires/bulk', [FraisScolaireController::class, 'bulkInit']);
    Route::get('/frais-scolaires/{frais}/paiements', [FraisScolaireController::class, 'paiements']);
    Route::post('/frais-scolaires/{frais}/paiements', [FraisScolaireController::class, 'addPaiement']);
    Route::apiResource('frais-scolaires', FraisScolaireController::class)->parameters(['frais-scolaires' => 'frais']);
    Route::get('/eleves-difficulte', [SuiviController::class, 'enDifficulte']);
    Route::get('/examens', [ExamenController::class, 'index']);
    Route::get('/dossiers-examen', [DossierExamenController::class, 'index']);
    Route::post('/dossiers-examen/{etudiant}', [DossierExamenController::class, 'store']);
    Route::get('/audits', [AuditController::class, 'index']);

    Route::get('/emploi-du-temps', [EmploiDuTempsController::class, 'index']);
    Route::apiResource('creneaux', CreneauController::class)->except(['show'])->parameters(['creneaux' => 'creneau']);

    Route::get('/alertes', [DashboardController::class, 'alerts'])->middleware('role:admin,proviseur,censeur,cpe');
    Route::post('/alertes/{alert}/verifier', [DashboardController::class, 'acknowledgeAlert'])->middleware('role:admin,proviseur,censeur,cpe');

    Route::get('/classes/{classe}/conseil', [ConseilClasseController::class, 'prepare']);
    Route::get('/classes/{classe}/conseils', [ConseilClasseController::class, 'index']);
    Route::post('/classes/{classe}/conseils', [ConseilClasseController::class, 'store']);
    Route::get('/classes/{classe}/conseils/{conseil}', [ConseilClasseController::class, 'show']);
    Route::apiResource('emploi-du-temps', EmploiDuTempsController::class)->except(['index', 'show'])->parameters(['emploi-du-temps' => 'emploiDuTemps']);

    Route::apiResource('classes', ClasseController::class);
    Route::apiResource('enseignants', EnseignantController::class);
    Route::get('/etudiants/lookup', [EtudiantController::class, 'lookup']);
    Route::apiResource('etudiants', EtudiantController::class);
    Route::apiResource('matieres', MatiereController::class);
    Route::apiResource('inscriptions', InscriptionController::class);
    Route::apiResource('notes', NoteController::class);
    Route::apiResource('users', UserController::class);

    Route::post('/notes/bulk', [NoteController::class, 'storeBulk']);
    Route::get('/etudiants/{etudiant}/bulletin', [BulletinController::class, 'show']);
    Route::get('/classes/{classe}/bulletins', [BulletinController::class, 'classe']);
    Route::get('/etudiants/{etudiant}/dossier', [DossierInscriptionController::class, 'show']);
    Route::post('/etudiants/{etudiant}/dossier', [DossierInscriptionController::class, 'update']);

    // ── Routes Super Admin (SaaS) ──────────────────────────────────
    Route::middleware('role:super_admin')->prefix('super-admin')->group(function () {
        Route::get('/dashboard', [SuperAdminController::class, 'dashboard']);
        Route::get('/etablissements', [SuperAdminController::class, 'indexEtablissements']);
        Route::post('/etablissements', [SuperAdminController::class, 'storeEtablissement']);
        Route::get('/etablissements/{etablissement}', [SuperAdminController::class, 'showEtablissement']);
        Route::put('/etablissements/{etablissement}', [SuperAdminController::class, 'updateEtablissement']);
        Route::delete('/etablissements/{etablissement}', [SuperAdminController::class, 'destroyEtablissement']);
        Route::post('/etablissements/{etablissement}/suspendre', [SuperAdminController::class, 'suspendreEtablissement']);
        Route::post('/etablissements/{etablissement}/reactiver', [SuperAdminController::class, 'reactiverEtablissement']);
    });
});
