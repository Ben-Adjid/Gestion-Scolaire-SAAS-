<?php

use App\Http\Controllers\Api\AnalyseController;
use App\Http\Controllers\Api\CahierTexteController;
use App\Http\Controllers\Api\ActualiteController;
use App\Http\Controllers\Api\ArchiveController;
use App\Http\Controllers\Api\DepenseController;
use App\Http\Controllers\Api\DemandeAbonnementController;
use App\Http\Controllers\Api\CalendrierController;
use App\Http\Controllers\Api\GroupeScolaireController;
use App\Http\Controllers\Api\TwoFactorController;
use App\Http\Controllers\Api\ExamenPlanningController;
use App\Http\Controllers\Api\PresenceDuJourController;
use App\Http\Controllers\Api\ComptabiliteController;
use App\Http\Controllers\Api\ImportExportController;
use App\Http\Controllers\Api\PaiementWebhookController;
use App\Http\Controllers\Api\ApiTokenController;
use App\Http\Controllers\Api\SupportController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AbsenceController;
use App\Http\Controllers\Api\AbsenceEnseignantController;
use App\Http\Controllers\Api\AppelController;
use App\Http\Controllers\Api\AssistantController;
use App\Http\Controllers\Api\AuditController;
use App\Http\Controllers\Api\BulletinController;
use App\Http\Controllers\Api\ClasseController;
use App\Http\Controllers\Api\ConformiteController;
use App\Http\Controllers\Api\JustificationController;
use App\Http\Controllers\Api\ParentController;
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

// Notification du prestataire de paiement en ligne (sans session : le statut est revérifié auprès de son API).
Route::post('/paiement-en-ligne/notification/{prestataire}', [\App\Http\Controllers\Api\PaiementEnLigneController::class, 'notification'])
    ->middleware('throttle:60,1')
    ->withoutMiddleware(['auth:sanctum', 'subscription', 'enforce_tenant', 'reserve_parents']);

// Référentiel public des pays pris en charge (aucune donnée personnelle).
Route::get('/pays', fn () => response()->json(
    collect(config('pays.liste'))->map(fn ($p, $code) => ['code' => $code] + $p)->values()
))->middleware('throttle:60,1')->withoutMiddleware(['auth:sanctum', 'subscription']);

// Mot de passe oublié : la réponse est identique, que le compte existe ou non.
Route::post('/mot-de-passe/oublie', [\App\Http\Controllers\Api\MotDePasseController::class, 'oublie'])
    ->middleware('throttle:5,1')
    ->withoutMiddleware(['auth:sanctum', 'subscription']);
Route::post('/mot-de-passe/reinitialiser', [\App\Http\Controllers\Api\MotDePasseController::class, 'reinitialiser'])
    ->middleware('throttle:10,1')
    ->withoutMiddleware(['auth:sanctum', 'subscription']);

// Webhook du prestataire de paiement : pas de session, authentification par signature HMAC.
Route::post('/webhooks/paiement', [PaiementWebhookController::class, 'recevoir'])
    ->withoutMiddleware(['auth:sanctum', 'subscription', 'enforce_tenant', 'reserve_parents']);

Route::middleware(['auth:sanctum', 'subscription', 'enforce_tenant', 'reserve_parents', 'donnees_scolaires', 'mot_de_passe_a_jour'])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/recherche', [\App\Http\Controllers\Api\RechercheController::class, 'index']);
    Route::post('/mot-de-passe/changer', [\App\Http\Controllers\Api\MotDePasseController::class, 'changer']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::prefix('abonnement')
        ->middleware('role:admin')
        ->withoutMiddleware(['subscription', 'enforce_tenant'])
        ->group(function () {
            Route::get('/options', [DemandeAbonnementController::class, 'options']);
            Route::get('/demandes', [DemandeAbonnementController::class, 'mesDemandes']);
            Route::post('/demandes', [DemandeAbonnementController::class, 'creer'])->middleware('throttle:10,1');
        });

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
    Route::get('/frais-scolaires/export', [FraisScolaireController::class, 'export'])->middleware('plan:export_comptable');
    Route::get('/frais-scolaires/relances', [ConformiteController::class, 'relances'])->middleware('plan:relances');
    Route::post('/frais-scolaires/{frais}/relancer', [ConformiteController::class, 'relancerIndividuellement'])
        ->middleware('plan:relances');
    Route::get('/etudiants/{etudiant}/export-rgpd', [ConformiteController::class, 'exportEleve'])->middleware('plan:rgpd');
    Route::get('/paiements/{paiement}/recu', [FraisScolaireController::class, 'recu'])->middleware('plan:recus');
    Route::get('/frais-scolaires/{frais}/paiements', [FraisScolaireController::class, 'paiements']);
    Route::post('/frais-scolaires/{frais}/paiements', [FraisScolaireController::class, 'addPaiement']);
    Route::apiResource('frais-scolaires', FraisScolaireController::class)->parameters(['frais-scolaires' => 'frais']);
    Route::get('/eleves-difficulte', [SuiviController::class, 'enDifficulte']);
    Route::get('/etudiants/{etudiant}/suivi', [SuiviController::class, 'fiche'])
        ->middleware(['plan:fiche_suivi', 'role:admin,proviseur,censeur,cpe']);
    Route::get('/examens', [ExamenController::class, 'index'])->middleware('plan:classes_examen');
    Route::get('/dossiers-examen', [DossierExamenController::class, 'index'])->middleware('plan:dossiers_examen');
    Route::get('/dossiers-examen/{etudiant}/fichiers/{type}', [DossierExamenController::class, 'fichier'])
        ->middleware(['plan:dossiers_examen', 'role:admin,proviseur,censeur,cpe,secretaire']);
    Route::post('/dossiers-examen/{etudiant}', [DossierExamenController::class, 'store'])->middleware('plan:dossiers_examen');
    Route::get('/audits', [AuditController::class, 'index'])->middleware('plan:journal_export');
    Route::get('/analyses', [AnalyseController::class, 'index'])->middleware(['role:admin,proviseur,censeur', 'plan:analyses_avancees']);
    Route::get('/comptabilite/synthese', [ComptabiliteController::class, 'synthese'])
        ->middleware(['plan:comptabilite_avancee', 'perm_or_role:comptabilite.voir,admin,proviseur,gestionnaire,agent_comptable']);

    // Données : import CSV des élèves et exports (CSV compatible Excel).
    Route::post('/import/etudiants', [ImportExportController::class, 'importerEtudiants'])
        ->middleware('perm_or_role:eleves.importer,admin,secretaire');
    Route::get('/export/etudiants', [ImportExportController::class, 'exporterEtudiants'])
        ->middleware('perm_or_role:donnees.exporter,admin,secretaire,proviseur');
    Route::get('/export/notes', [ImportExportController::class, 'exporterNotes'])
        ->middleware('perm_or_role:donnees.exporter,admin,secretaire,proviseur');
    Route::get('/permissions', [UserController::class, 'catalogue']);

    // Calendrier scolaire (Standard) : lecture pour tous, saisie pour la direction.
    Route::get('/calendrier', [CalendrierController::class, 'index'])->middleware('plan:calendrier_scolaire');
    Route::post('/calendrier', [CalendrierController::class, 'store'])
        ->middleware(['plan:calendrier_scolaire', 'role:admin,proviseur']);
    Route::delete('/calendrier/{evenement}', [CalendrierController::class, 'destroy'])
        ->middleware(['plan:calendrier_scolaire', 'role:admin,proviseur']);

    // Archives annuelles scellées (Entreprise) : création, consultation, vérification. Aucune modification.
    Route::get('/archives', [ArchiveController::class, 'index'])->middleware(['plan:archivage', 'role:admin']);
    Route::post('/archives', [ArchiveController::class, 'store'])->middleware(['plan:archivage', 'role:admin']);
    Route::get('/archives/{archive}', [ArchiveController::class, 'show'])->middleware(['plan:archivage', 'role:admin']);
    Route::get('/archives/{archive}/verifier', [ArchiveController::class, 'verifier'])->middleware(['plan:archivage', 'role:admin']);

    // Dépenses (Entreprise) : salaires, fournitures, maintenance, transport.
    Route::get('/depenses/export', [DepenseController::class, 'export'])
        ->middleware(['plan:comptabilite_avancee', 'role:admin,proviseur,gestionnaire,agent_comptable']);
    Route::get('/comptabilite/export', [ComptabiliteController::class, 'export'])
        ->middleware(['plan:comptabilite_avancee', 'perm_or_role:comptabilite.voir,admin,proviseur,gestionnaire,agent_comptable']);
    Route::get('/depenses', [DepenseController::class, 'index'])
        ->middleware(['plan:comptabilite_avancee', 'role:admin,proviseur,gestionnaire,agent_comptable']);
    Route::post('/depenses', [DepenseController::class, 'store'])
        ->middleware(['plan:comptabilite_avancee', 'role:admin,proviseur,gestionnaire,agent_comptable']);
    Route::delete('/depenses/{depense}', [DepenseController::class, 'destroy'])
        ->middleware(['plan:comptabilite_avancee', 'role:admin,proviseur,gestionnaire,agent_comptable']);

    // Multi-sites (Entreprise) : synthèse consolidée du groupe, par un administrateur d'un site du groupe.
    Route::get('/groupe/synthese', [GroupeScolaireController::class, 'synthese'])->middleware('role:admin');

    // Double authentification du compte connecté.
    Route::get('/2fa/statut', [TwoFactorController::class, 'statut']);
    Route::post('/2fa/activer', [TwoFactorController::class, 'activer']);
    Route::post('/2fa/confirmer', [TwoFactorController::class, 'confirmer']);
    Route::post('/2fa/desactiver', [TwoFactorController::class, 'desactiver']);

    // Présence du jour en temps réel (VIP) : direction et vie scolaire.
    Route::get('/presence-du-jour', [PresenceDuJourController::class, 'index'])
        ->middleware(['plan:presence_temps_reel', 'role:admin,proviseur,censeur,cpe,secretaire']);

    // Planning des examens (VIP) : consultation pour l'équipe, saisie pour la direction.
    Route::get('/planning-examens', [ExamenPlanningController::class, 'index'])
        ->middleware(['plan:planning_examens', 'role:admin,proviseur,censeur,cpe,surveillant,enseignant,secretaire']);
    Route::post('/planning-examens', [ExamenPlanningController::class, 'store'])
        ->middleware(['plan:planning_examens', 'role:admin,proviseur,censeur']);
    Route::delete('/planning-examens/{epreuve}', [ExamenPlanningController::class, 'destroy'])
        ->middleware(['plan:planning_examens', 'role:admin,proviseur,censeur']);

    // Fil d'actualité (VIP) : lecture pour tous les comptes de l'établissement, publication pour la direction.
    Route::get('/actualites', [ActualiteController::class, 'index'])->middleware('plan:fil_actualite');
    Route::post('/actualites', [ActualiteController::class, 'store'])
        ->middleware(['plan:fil_actualite', 'role:admin,proviseur']);
    Route::delete('/actualites/{actualite}', [ActualiteController::class, 'destroy'])
        ->middleware(['plan:fil_actualite', 'role:admin,proviseur']);

    // Cahier de texte : enseignants (saisie), direction et parents (consultation).
    Route::get('/cahier-texte', [CahierTexteController::class, 'index'])
        ->middleware(['plan:cahier_texte', 'role:enseignant,admin,proviseur,censeur,parent']);
    Route::post('/cahier-texte', [CahierTexteController::class, 'store'])
        ->middleware(['plan:cahier_texte', 'role:enseignant']);
    Route::put('/cahier-texte/{cahier}', [CahierTexteController::class, 'update'])
        ->middleware(['plan:cahier_texte', 'role:enseignant']);
    Route::delete('/cahier-texte/{cahier}', [CahierTexteController::class, 'destroy'])
        ->middleware(['plan:cahier_texte', 'role:enseignant']);

    // Formule Entreprise : clés API et support prioritaire.
    Route::middleware('plan:api')->prefix('api-tokens')->group(function () {
        Route::get('/', [ApiTokenController::class, 'index']);
        Route::post('/', [ApiTokenController::class, 'store']);
        Route::delete('/{id}', [ApiTokenController::class, 'destroy']);
    });
    Route::middleware('plan:support_prioritaire')->prefix('support')->group(function () {
        Route::get('/', [SupportController::class, 'index']);
        Route::post('/', [SupportController::class, 'store']);
    });

    Route::get('/emploi-du-temps', [EmploiDuTempsController::class, 'index']);
    Route::apiResource('creneaux', CreneauController::class)->except(['show'])->parameters(['creneaux' => 'creneau']);

    Route::prefix('parent')->middleware('plan:espace_parent')->group(function () {
        Route::get('/enfants', [ParentController::class, 'enfants']);
        Route::get('/enfants/{etudiant}/resume', [ParentController::class, 'resume']);
        Route::post('/enfants/{etudiant}/absences/{absence}/justifier', [ParentController::class, 'justifier']);
        Route::get('/justifications', [ParentController::class, 'mesJustifications']);
        // Notifications à la carte et autorisations de sortie signées.
        Route::get('/preferences-notifications', [\App\Http\Controllers\Api\AutorisationSortieController::class, 'preferences']);
        Route::put('/preferences-notifications', [\App\Http\Controllers\Api\AutorisationSortieController::class, 'enregistrerPreferences']);
        Route::get('/sorties', [\App\Http\Controllers\Api\AutorisationSortieController::class, 'mesSorties']);
        Route::post('/enfants/{etudiant}/sorties', [\App\Http\Controllers\Api\AutorisationSortieController::class, 'signer'])->middleware('throttle:20,1');
        Route::post('/sorties/{autorisation}/annuler', [\App\Http\Controllers\Api\AutorisationSortieController::class, 'annuler'])->whereNumber('autorisation');
        Route::get('/sorties/{autorisation}/document', [\App\Http\Controllers\Api\AutorisationSortieController::class, 'document'])->whereNumber('autorisation');
    });
    // Paiement en ligne des frais scolaires.
    Route::prefix('parent')->middleware('plan:paiement_en_ligne')->group(function () {
        Route::post('/frais/{frais}/payer', [\App\Http\Controllers\Api\PaiementEnLigneController::class, 'payer'])->middleware('throttle:10,1');
        Route::get('/paiements-en-ligne/{reference}', [\App\Http\Controllers\Api\PaiementEnLigneController::class, 'suivre'])->middleware('throttle:30,1');
        Route::post('/paiements-en-ligne/{reference}/simuler', [\App\Http\Controllers\Api\PaiementEnLigneController::class, 'simuler']);
    });
    Route::get('/etablissement/paiement-en-ligne', [\App\Http\Controllers\Api\PaiementEnLigneController::class, 'configuration'])->middleware('plan:paiement_en_ligne');
    Route::put('/etablissement/paiement-en-ligne', [\App\Http\Controllers\Api\PaiementEnLigneController::class, 'configurer'])->middleware('plan:paiement_en_ligne');

    // Rendez-vous parents-professeurs.
    Route::prefix('parent')->middleware('plan:espace_parent')->group(function () {
        Route::get('/rendez-vous', [\App\Http\Controllers\Api\RendezVousController::class, 'mesRendezVous']);
        Route::get('/enfants/{etudiant}/enseignants', [\App\Http\Controllers\Api\RendezVousController::class, 'enseignantsDeLEnfant']);
        Route::post('/enfants/{etudiant}/rendez-vous', [\App\Http\Controllers\Api\RendezVousController::class, 'demander'])->middleware('throttle:20,1');
        Route::post('/rendez-vous/{rendezVous}/annuler', [\App\Http\Controllers\Api\RendezVousController::class, 'annuler'])->whereNumber('rendezVous');
    });
    Route::prefix('rendez-vous')->middleware('plan:espace_parent')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\RendezVousController::class, 'index']);
        Route::post('/{rendezVous}/confirmer', [\App\Http\Controllers\Api\RendezVousController::class, 'confirmer'])->whereNumber('rendezVous');
        Route::post('/{rendezVous}/refuser', [\App\Http\Controllers\Api\RendezVousController::class, 'refuser'])->whereNumber('rendezVous');
    });
    Route::prefix('autorisations-sortie')->middleware('plan:espace_parent')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\AutorisationSortieController::class, 'index']);
        Route::post('/{autorisation}/decision', [\App\Http\Controllers\Api\AutorisationSortieController::class, 'decider'])->whereNumber('autorisation');
        Route::post('/{autorisation}/sortie', [\App\Http\Controllers\Api\AutorisationSortieController::class, 'marquerSortie'])->whereNumber('autorisation');
        Route::get('/{autorisation}/document', [\App\Http\Controllers\Api\AutorisationSortieController::class, 'document'])->whereNumber('autorisation');
    });
    // Compte parent d'un élève déjà inscrit : création ou rattachement (frères et sœurs).
    Route::get('/etudiants/{etudiant}/parents', [EtudiantController::class, 'parents'])->middleware(['plan:espace_parent', 'role:admin,proviseur,secretaire']);
    Route::post('/etudiants/{etudiant}/compte-parent', [EtudiantController::class, 'compteParent'])->middleware(['plan:espace_parent', 'role:admin,proviseur,secretaire']);
    Route::post('/etudiants/{etudiant}/parents/{parent}', [ParentController::class, 'lier'])->middleware('role:admin,secretaire');
    Route::delete('/etudiants/{etudiant}/parents/{parent}', [ParentController::class, 'delier'])->middleware('role:admin,secretaire');
    Route::get('/justifications', [JustificationController::class, 'index']);
    Route::post('/justifications/{justification}/decision', [JustificationController::class, 'decider']);

    Route::get('/a-traiter', [\App\Http\Controllers\Api\ATraiterController::class, 'index'])->middleware('role:admin,proviseur,censeur,cpe');
    Route::get('/alertes', [DashboardController::class, 'alerts'])->middleware(['role:admin,proviseur,censeur,cpe', 'plan:alertes']);
    Route::post('/alertes/{alert}/verifier', [DashboardController::class, 'acknowledgeAlert'])->middleware(['role:admin,proviseur,censeur,cpe', 'plan:alertes']);

    Route::get('/classes/{classe}/conseil', [ConseilClasseController::class, 'prepare'])->middleware('plan:conseil_classe');
    Route::get('/classes/{classe}/conseils', [ConseilClasseController::class, 'index'])->middleware('plan:conseil_classe');
    Route::post('/classes/{classe}/conseils', [ConseilClasseController::class, 'store'])->middleware('plan:conseil_classe');
    Route::get('/classes/{classe}/conseils/{conseil}', [ConseilClasseController::class, 'show'])->middleware('plan:conseil_classe');
    Route::apiResource('emploi-du-temps', EmploiDuTempsController::class)->except(['index', 'show'])->parameters(['emploi-du-temps' => 'emploiDuTemps']);

    Route::apiResource('classes', ClasseController::class)->parameters(['classes' => 'classe']);
    Route::apiResource('enseignants', EnseignantController::class);
    Route::get('/etudiants/doublons', [EtudiantController::class, 'doublons'])
        ->middleware('role:admin,proviseur,censeur,secretaire');
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
    Route::get('/etudiants/{etudiant}/dossier/fichiers/{type}', [DossierInscriptionController::class, 'fichier']);
    Route::post('/etudiants/{etudiant}/dossier', [DossierInscriptionController::class, 'update']);

    // ── Routes Super Admin (SaaS) ──────────────────────────────────
    Route::middleware('role:super_admin')->prefix('super-admin')->group(function () {
        Route::get('/dashboard', [SuperAdminController::class, 'dashboard']);
        Route::get('/journal', [SuperAdminController::class, 'journal']);
        Route::get('/erreurs', [SuperAdminController::class, 'erreurs']);
        Route::delete('/erreurs/{erreur}', [SuperAdminController::class, 'resoudreErreur'])->whereNumber('erreur');
        Route::get('/etablissements/export', [SuperAdminController::class, 'exporterEtablissements']);
        Route::post('/annonces', [SuperAdminController::class, 'annoncer']);
        Route::get('/etablissements', [SuperAdminController::class, 'indexEtablissements']);
        Route::post('/etablissements', [SuperAdminController::class, 'storeEtablissement']);
        Route::get('/etablissements/{etablissement}', [SuperAdminController::class, 'showEtablissement']);
        Route::put('/etablissements/{etablissement}', [SuperAdminController::class, 'updateEtablissement']);
        Route::delete('/etablissements/{etablissement}', [SuperAdminController::class, 'destroyEtablissement']);
        Route::post('/etablissements/{etablissement}/suspendre', [SuperAdminController::class, 'suspendreEtablissement']);
        Route::post('/etablissements/{etablissement}/reactiver', [SuperAdminController::class, 'reactiverEtablissement']);
        Route::get('/groupes', [GroupeScolaireController::class, 'index']);
        Route::post('/groupes', [GroupeScolaireController::class, 'store']);
        Route::put('/groupes/{groupe}', [GroupeScolaireController::class, 'update']);
        Route::delete('/groupes/{groupe}', [GroupeScolaireController::class, 'destroy']);
        Route::post('/etablissements/{etablissement}/groupe', [GroupeScolaireController::class, 'lier']);
        Route::post('/etablissements/{etablissement}/relancer', [SuperAdminController::class, 'relancer']);
        Route::post('/etablissements/{etablissement}/essai', [SuperAdminController::class, 'demarrerEssai']);
        Route::get('/paiements-abonnement/{paiement}/facture', [SuperAdminController::class, 'factureAbonnement']);
        Route::post('/etablissements/{etablissement}/reinitialiser-admin', [SuperAdminController::class, 'reinitialiserAdmin']);
        Route::get('/paiements-abonnement/{paiement}/recu', [SuperAdminController::class, 'recuAbonnement']);
        Route::post('/etablissements/{etablissement}/renouveler', [SuperAdminController::class, 'renouveler']);
        Route::get('/etablissements/{etablissement}/paiements-abonnement', [SuperAdminController::class, 'paiementsAbonnement']);
        Route::get('/demandes-abonnement', [DemandeAbonnementController::class, 'index']);
        Route::get('/demandes-abonnement/{demande}/justificatif', [DemandeAbonnementController::class, 'justificatif']);
        Route::post('/demandes-abonnement/{demande}/approuver', [DemandeAbonnementController::class, 'approuver']);
        Route::post('/demandes-abonnement/{demande}/refuser', [DemandeAbonnementController::class, 'refuser']);
        Route::get('/support', [SuperAdminController::class, 'supportIndex']);
        Route::post('/support/{demande}', [SuperAdminController::class, 'supportRepondre']);
    });
});
