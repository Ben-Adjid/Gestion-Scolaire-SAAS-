<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Etablissement;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * SuperAdminController
 * Accessible uniquement aux utilisateurs ayant le rôle "superadmin".
 * Gère les établissements, leurs abonnements et leurs admins locaux.
 */
class SuperAdminController extends Controller
{
    // ──────────────────────────────────────────────
    // ÉTABLISSEMENTS
    // ──────────────────────────────────────────────

    /** Liste tous les établissements (avec stats). */
    public function indexEtablissements(Request $request): JsonResponse
    {
        $etablissements = Etablissement::withCount(['classes', 'etudiants', 'users'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($e) => $this->formatEtablissement($e) + ['sante' => \App\Services\ScoreSante::pour($e)]);

        return response()->json(['data' => $etablissements]);
    }

    /** Détail d'un établissement. */
    public function showEtablissement(Etablissement $etablissement): JsonResponse
    {
        $etablissement->loadCount(['classes', 'etudiants', 'users']);

        return response()->json($this->formatEtablissement($etablissement) + [
            'sante' => \App\Services\ScoreSante::pour($etablissement),
            'administrateurs' => User::withoutGlobalScopes()->where('etablissement_id', $etablissement->id)->where('role', 'admin')->orderBy('id')->get(['id', 'name', 'email'])->values(),
            'groupe' => $etablissement->groupe_scolaire_id ? AppModelsGroupeScolaire::find($etablissement->groupe_scolaire_id)?->nom : null,
        ]);
    }

    /** Créer un nouvel établissement + son administrateur local. */
    public function storeEtablissement(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nom'              => 'required|string|max:255',
            'devise'           => 'nullable|string|max:255',
            'ministere'        => 'nullable|string|max:255',
            'adresse'          => 'nullable|string|max:500',
            'telephone'        => 'nullable|string|max:50',
            'annee_scolaire'   => 'nullable|string|max:20',
            'prefixe_matricule'=> 'nullable|string|max:10',
            'responsable'      => 'nullable|string|max:255',
            // Plan et abonnement
            'plan'             => ['required', Rule::in(array_keys(config('plans.plans')))],
            'abonnement_fin'   => 'nullable|date',
            // Compte admin de l'établissement
            'admin_name'       => 'required|string|max:255',
            'admin_email'      => 'required|email|unique:users,email',
            'admin_password'   => 'required|string|min:8',
        ]);

        [$etablissement] = DB::transaction(function () use ($data) {
            $etablissement = Etablissement::create([
                'nom'               => $data['nom'],
                'devise'            => $data['devise'] ?? 'Unité — Travail — Progrès',
                'ministere'         => $data['ministere'] ?? null,
                'adresse'           => $data['adresse'] ?? null,
                'telephone'         => $data['telephone'] ?? null,
                'annee_scolaire'    => $data['annee_scolaire'] ?? date('Y') . '-' . (date('Y') + 1),
                'prefixe_matricule' => strtoupper($data['prefixe_matricule'] ?? 'SCH'),
                'responsable'       => $data['responsable'] ?? null,
                'plan'              => $data['plan'],
                'statut_abonnement' => 'actif',
                'abonnement_fin'    => $data['abonnement_fin'] ?? null,
            ]);

            User::withoutGlobalScopes()->create([
                'name'              => $data['admin_name'],
                'email'             => $data['admin_email'],
                'password'          => Hash::make($data['admin_password']),
                'role'              => 'admin',
                'etablissement_id'  => $etablissement->id,
            ])->forceFill(['doit_changer_mot_de_passe' => true])->save();

            return [$etablissement];
        });

        \App\Services\AuditLogger::log($request->user(), 'creation_etablissement', 'etablissements', $etablissement->id,
            "Établissement « {$etablissement->nom} » créé, formule {$data['plan']}.");

        // Bienvenue à l'administrateur de la nouvelle école (un échec d'envoi ne bloque pas la création).
        try {
            \Illuminate\Support\Facades\Mail::to($data['admin_email'])->send(new \App\Mail\CompteCree(
                $data['admin_name'], 'Administrateur', $etablissement->nom,
            ));
        } catch (\Throwable $e) {
            // Le compte est créé même si l'e-mail n'a pas pu partir.
        }

        return response()->json([
            'message'       => "Établissement \"{$etablissement->nom}\" créé avec succès.",
            'etablissement' => $this->formatEtablissement($etablissement->loadCount(['classes', 'etudiants', 'users'])),
        ], 201);
    }

    /** Mettre à jour un établissement (plan, abonnement, infos). */
    public function updateEtablissement(Request $request, Etablissement $etablissement): JsonResponse
    {
        $data = $request->validate([
            'nom'               => 'sometimes|string|max:255',
            'devise'            => 'nullable|string|max:255',
            'ministere'         => 'nullable|string|max:255',
            'adresse'           => 'nullable|string|max:500',
            'telephone'         => 'nullable|string|max:50',
            'annee_scolaire'    => 'nullable|string|max:20',
            'prefixe_matricule' => 'nullable|string|max:10',
            'responsable'       => 'nullable|string|max:255',
            'plan'              => ['nullable', Rule::in(array_keys(config('plans.plans')))],
            'statut_abonnement' => ['nullable', Rule::in(['actif', 'suspendu', 'expire', 'essai'])],
            'abonnement_fin'    => 'nullable|date',
        ]);

        // Le préfixe de matricule est obligatoire : un champ vide garde la valeur actuelle.
        if (array_key_exists('prefixe_matricule', $data) && blank($data['prefixe_matricule'])) {
            unset($data['prefixe_matricule']);
        } elseif (isset($data['prefixe_matricule'])) {
            $data['prefixe_matricule'] = strtoupper($data['prefixe_matricule']);
        }

        $etablissement->update($data);

        \App\Services\AuditLogger::log($request->user(), 'modification_etablissement', 'etablissements', $etablissement->id,
            "Établissement « {$etablissement->nom} » modifié : ".implode(', ', array_keys($data)).'.');

        return response()->json([
            'message'       => 'Établissement mis à jour.',
            'etablissement' => $this->formatEtablissement($etablissement->loadCount(['classes', 'etudiants', 'users'])),
        ]);
    }

    /** Suspendre un établissement (bloque la connexion de ses utilisateurs). */
    public function suspendreEtablissement(Request $request, Etablissement $etablissement): JsonResponse
    {
        $etablissement->update(['statut_abonnement' => 'suspendu']);
        \App\Services\AuditLogger::log($request->user(), 'suspension_etablissement', 'etablissements', $etablissement->id,
            "Établissement « {$etablissement->nom} » suspendu.");
        return response()->json(['message' => "Établissement \"{$etablissement->nom}\" suspendu."]);
    }

    /** Réactiver un établissement suspendu. */
    public function reactiverEtablissement(Request $request, Etablissement $etablissement): JsonResponse
    {
        $etablissement->update(['statut_abonnement' => 'actif']);
        \App\Services\AuditLogger::log($request->user(), 'reactivation_etablissement', 'etablissements', $etablissement->id,
            "Établissement « {$etablissement->nom} » réactivé.");
        return response()->json(['message' => "Établissement \"{$etablissement->nom}\" réactivé."]);
    }

    /** Supprimer un établissement et toutes ses données. */
    public function destroyEtablissement(Request $request, Etablissement $etablissement): JsonResponse
    {
        $nom = $etablissement->nom;
        // Suppression irréversible : le nom exact de l'école doit être ressaisi.
        $request->validate(['confirmation' => ['required', 'string', Rule::in([$nom])]], [
            'confirmation.in' => 'Le nom saisi ne correspond pas à l’établissement.',
        ]);

        \App\Services\AuditLogger::log($request->user(), 'suppression_etablissement', 'etablissements', $etablissement->id,
            "Établissement « {$nom} » supprimé définitivement.");
        $etablissement->delete();
        return response()->json(['message' => "Établissement \"{$nom}\" supprimé."]);
    }

    /**
     * Renouvelle l'abonnement d'un établissement et enregistre le paiement (hors ligne).
     * Si l'abonnement est encore valide, la nouvelle période part de sa date de fin.
     */
    public function renouveler(Request $request, Etablissement $etablissement): JsonResponse
    {
        $data = $request->validate([
            'plan' => ['nullable', Rule::in(array_keys(config('plans.plans')))],
            'duree_mois' => 'required|integer|min:1|max:24',
            'montant' => 'required|integer|min:0',
            'mode_paiement' => 'required|in:virement,especes,mobile_money,carte',
            'reference' => 'nullable|string|max:100',
        ]);

        $plan = $data['plan'] ?? $etablissement->planCode();

        $paiement = app(\App\Services\AbonnementService::class)->renouveler(
            $etablissement,
            $plan,
            (int) $data['duree_mois'],
            (int) $data['montant'],
            $data['mode_paiement'],
            $data['reference'] ?? null,
            $request->user(),
        );

        $fin = $paiement->periode_fin;

        return response()->json([
            'message' => "Abonnement renouvelé jusqu'au {$fin->format('d/m/Y')}.",
            'etablissement' => $this->formatEtablissement($etablissement->fresh()->loadCount(['classes', 'etudiants', 'users'])),
        ]);
    }

    /** Historique des paiements d'abonnement d'un établissement. */
    public function paiementsAbonnement(Etablissement $etablissement): JsonResponse
    {
        return response()->json(
            \App\Models\AbonnementPaiement::where('etablissement_id', $etablissement->id)->latest('id')->get()
        );
    }

    /** Toutes les demandes de support prioritaire, toutes formules confondues. */
    public function supportIndex(): JsonResponse
    {
        return response()->json(
            \App\Models\SupportDemande::with(['auteur:id,name', 'etablissement:id,nom'])->latest('id')->get()
        );
    }

    /** Répond à une demande de support et met à jour son statut. */
    public function supportRepondre(Request $request, \App\Models\SupportDemande $demande): JsonResponse
    {
        $data = $request->validate([
            'statut' => ['required', Rule::in(\App\Models\SupportDemande::STATUTS)],
            'reponse' => 'nullable|string|max:3000',
        ]);

        $demande->update($data);

        \App\Services\AuditLogger::log($request->user(), 'support_repondu', 'SupportDemande', $demande->id,
            "Demande « {$demande->sujet} » : {$data['statut']}");

        return response()->json(['message' => 'Demande mise à jour.', 'demande' => $demande->fresh()]);
    }

    /**
     * Envoie une annonce de la plateforme aux administrateurs des établissements ciblés.
     */
    public function annoncer(Request $request): JsonResponse
    {
        $data = $request->validate([
            'titre' => 'required|string|max:255',
            'contenu' => 'required|string|max:5000',
            'cible' => 'required|in:tous,plan',
            'plan' => 'required_if:cible,plan|nullable|in:'.implode(',', array_keys(config('plans.plans'))),
        ]);

        $etablissements = Etablissement::query()
            ->when($data['cible'] === 'plan', fn ($q) => $q->where('plan', $data['plan']))
            ->get();

        $envoyes = 0;
        foreach ($etablissements as $etablissement) {
            $destinataires = User::where('etablissement_id', $etablissement->id)->where('role', 'admin')->get();
            foreach ($destinataires as $destinataire) {
                \App\Models\Message::create([
                    'sender_id' => $request->user()->id,
                    'recipient_id' => $destinataire->id,
                    'subject' => $data['titre'],
                    'body' => $data['contenu'],
                    'type' => 'annonce',
                    'etablissement_id' => $etablissement->id,
                ]);
                $envoyes++;
            }
        }

        \App\Services\AuditLogger::log($request->user(), 'annonce_plateforme', 'SuperAdmin', null,
            "Annonce « {$data['titre']} » envoyée à {$envoyes} administrateur(s)");

        return response()->json(['message' => "Annonce envoyée à {$envoyes} administrateur(s).", 'destinataires' => $envoyes], 201);
    }

    // ──────────────────────────────────────────────
    // TABLEAU DE BORD GLOBAL
    // ──────────────────────────────────────────────

    /** Statistiques globales de la plateforme SaaS. */
    public function dashboard(): JsonResponse
    {
        $stats = [
            'total_etablissements' => Etablissement::count(),
            'actifs'               => Etablissement::where('statut_abonnement', 'actif')->count(),
            'suspendus'            => Etablissement::where('statut_abonnement', 'suspendu')->count(),
            'expires'              => Etablissement::where('statut_abonnement', 'expire')->count(),
            'essai'                => Etablissement::where('statut_abonnement', 'essai')->count(),
            'par_plan'             => Etablissement::selectRaw('plan, COUNT(*) as total')
                                        ->groupBy('plan')
                                        ->pluck('total', 'plan'),
            'total_eleves'         => \App\Models\Etudiant::withoutGlobalScopes()->count(),
            'total_enseignants'    => \App\Models\Enseignant::withoutGlobalScopes()->count(),
        ];

        $recents = Etablissement::orderByDesc('created_at')
            ->take(5)
            ->withCount(['classes', 'etudiants'])
            ->get()
            ->map(fn ($e) => $this->formatEtablissement($e));

        // Revenus récurrents : chaque établissement actif paie le tarif mensuel de sa formule.
        $tarifs = collect(config('plans.plans', []))->map(fn ($p) => (int) $p['prix_fcfa']);
        $mrr = (int) Etablissement::where('statut_abonnement', 'actif')
            ->pluck('plan')
            ->sum(fn ($plan) => $tarifs[$plan] ?? $tarifs[config('plans.defaut')] ?? 0);

        // Encaissements des 12 derniers mois, du plus ancien au plus récent.
        $debut = now()->startOfMonth()->subMonths(11);
        $encaissements = \App\Models\AbonnementPaiement::where('created_at', '>=', $debut)
            ->get(['montant', 'created_at']);
        $parMois = [];
        for ($i = 0; $i < 12; $i++) {
            $mois = $debut->copy()->addMonths($i);
            $parMois[] = [
                'mois' => $mois->format('Y-m'),
                'libelle' => $mois->translatedFormat('M Y'),
                'montant' => (int) $encaissements
                    ->filter(fn ($p) => $p->created_at->format('Y-m') === $mois->format('Y-m'))
                    ->sum('montant'),
            ];
        }

        $encaisseMois = (int) \App\Models\AbonnementPaiement::where('created_at', '>=', now()->subDays(30))->sum('montant');

        // Établissements dont l'abonnement arrive à échéance dans les 30 prochains jours, ou déjà expiré.
        $renouvellements = Etablissement::query()
            ->whereNotNull('abonnement_fin')
            ->where('abonnement_fin', '<=', now()->addDays(30))
            ->orderBy('abonnement_fin')
            ->take(10)
            ->get(['id', 'nom', 'plan', 'statut_abonnement', 'abonnement_fin'])
            ->map(fn ($e) => [
                'id' => $e->id,
                'nom' => $e->nom,
                'plan' => $e->plan ?? 'standard',
                'statut_abonnement' => $e->statut_abonnement ?? 'actif',
                'abonnement_fin' => $e->abonnement_fin?->format('d/m/Y'),
                'jours_restants' => $e->jours_restants,
            ]);

        $stats += [
            'mrr_fcfa' => $mrr,
            'encaisse_30j_fcfa' => $encaisseMois,
            'part_actifs' => $stats['total_etablissements'] > 0
                ? round($stats['actifs'] / $stats['total_etablissements'] * 100)
                : 0,
        ];

        return response()->json([
            'stats'           => $stats,
            'recents'         => $recents,
            'encaissements'   => $parMois,
            'renouvellements' => $renouvellements,
        ]);
    }

    // ──────────────────────────────────────────────
    // HELPERS
    // ──────────────────────────────────────────────

    /**
     * Démarre un essai gratuit : l'école accède à l'application pendant la durée choisie (30 jours par défaut).
     * À l'échéance, la commande d'expiration suspend l'accès ; un renouvellement convertit l'essai en abonnement.
     */
    public function demarrerEssai(Request $request, Etablissement $etablissement): JsonResponse
    {
        $donnees = $request->validate(['jours' => 'nullable|integer|min:1|max:90']);
        $jours = (int) ($donnees['jours'] ?? 30);

        $etablissement->update([
            'statut_abonnement' => 'essai',
            'abonnement_fin' => now()->addDays($jours)->toDateString(),
        ]);

        \App\Services\AuditLogger::log($request->user(), 'essai_gratuit', 'etablissements', $etablissement->id,
            "Essai gratuit de {$jours} jours démarré pour « {$etablissement->nom} ».");

        return response()->json([
            'message' => "Essai gratuit de {$jours} jours démarré.",
            'etablissement' => $this->formatEtablissement($etablissement->fresh()->loadCount(['classes', 'etudiants', 'users'])),
        ]);
    }

    /** Facture d'un paiement d'abonnement (même mise en forme que le reçu, titre « Facture »). */
    public function factureAbonnement(\App\Models\AbonnementPaiement $paiement)
    {
        return $this->recuAbonnement($paiement, 'Facture');
    }

    /** Relance par e-mail des administrateurs d'une école (abonnement proche de l'échéance ou expiré). */
    public function relancer(Request $request, Etablissement $etablissement): JsonResponse
    {
        $admins = User::withoutGlobalScopes()
            ->where('etablissement_id', $etablissement->id)
            ->where('role', 'admin')
            ->get();

        if ($admins->isEmpty()) {
            return response()->json(['message' => "Cet établissement n'a aucun administrateur à relancer."], 422);
        }

        $fin = $etablissement->abonnement_fin?->format('d/m/Y');
        $jours = (int) ($etablissement->jours_restants ?? 0);
        $corps = $jours < 0
            ? "Votre abonnement a expiré le {$fin}. Renouvelez-le auprès de la plateforme pour retrouver l'accès complet à votre espace."
            : "Votre abonnement arrive à échéance le {$fin}. Pensez à le renouveler pour éviter toute interruption.";

        $envoyes = 0;
        foreach ($admins as $admin) {
            try {
                \Illuminate\Support\Facades\Mail::to($admin->email)
                    ->send(new \App\Mail\RelanceAbonnement($etablissement, max(0, $jours), $corps));
                $envoyes++;
            } catch (\Throwable $e) {
                // Un échec d'envoi n'interrompt pas les autres destinataires.
            }
        }

        \App\Services\AuditLogger::log($request->user(), 'relance_abonnement', 'etablissements', $etablissement->id,
            "Relance envoyée à {$envoyes} administrateur(s) de « {$etablissement->nom} ».");

        return response()->json(['message' => "Relance envoyée à {$envoyes} administrateur(s).", 'envoyes' => $envoyes]);
    }

    /**
     * Réinitialise le mot de passe du premier administrateur de l'école et déconnecte ses sessions.
     * Le mot de passe temporaire n'est affiché qu'une seule fois, à la plateforme.
     */
    public function reinitialiserAdmin(Request $request, Etablissement $etablissement): JsonResponse
    {
        $admin = User::withoutGlobalScopes()
            ->where('etablissement_id', $etablissement->id)
            ->where('role', 'admin')
            ->orderBy('id')
            ->first();

        if (! $admin) {
            return response()->json(['message' => "Cet établissement n'a aucun administrateur."], 422);
        }

        $temporaire = \Illuminate\Support\Str::password(12, true, true, false);
        $admin->forceFill(['password' => Hash::make($temporaire), 'doit_changer_mot_de_passe' => true])->save();
        $admin->tokens()->delete();

        \App\Services\AuditLogger::log($request->user(), 'reinitialisation_mot_de_passe', 'users', $admin->id,
            "Mot de passe de l'administrateur de « {$etablissement->nom} » réinitialisé.");

        return response()->json([
            'message' => 'Mot de passe réinitialisé. Transmettez-le à l’administrateur : il ne sera plus affiché.',
            'email' => $admin->email,
            'mot_de_passe_temporaire' => $temporaire,
        ]);
    }

    /** Reçu d'un paiement d'abonnement (HTML imprimable, à enregistrer en PDF depuis le navigateur). */
    public function recuAbonnement(\App\Models\AbonnementPaiement $paiement, string $titre = 'Reçu d’abonnement')
    {
        $etablissement = Etablissement::withoutGlobalScopes()->find($paiement->etablissement_id);
        $numero = 'ABO-'.str_pad((string) $paiement->id, 6, '0', STR_PAD_LEFT);
        $e = fn ($v) => e((string) $v);
        $formule = config("plans.plans.{$paiement->plan}.libelle", $paiement->plan);

        $html = '<!doctype html><html lang="fr"><head><meta charset="utf-8"><title>Reçu '.$numero.'</title>'
            .'<style>body{font-family:Georgia,serif;color:#15302A;padding:40px;max-width:720px;margin:auto}'
            .'h1{font-size:22px;margin:0}table{width:100%;border-collapse:collapse;margin-top:20px}'
            .'td{padding:8px;border-bottom:1px solid #E4DED3}.montant{font-size:20px;font-weight:bold}'
            .'@media print{button{display:none}}</style></head><body>'
            .'<h1>Plateforme de gestion scolaire</h1>'
            .'<p>'.$e($titre).' n° '.$numero.'</p>'
            .'<table>'
            .'<tr><td>Établissement</td><td>'.$e($etablissement?->nom).'</td></tr>'
            .'<tr><td>Formule</td><td>'.$e($formule).'</td></tr>'
            .'<tr><td>Période</td><td>'.$e($paiement->periode_debut?->format('d/m/Y')).' au '.$e($paiement->periode_fin?->format('d/m/Y')).'</td></tr>'
            .'<tr><td>Mode</td><td>'.$e($paiement->mode_paiement).'</td></tr>'
            .'<tr><td>Référence</td><td>'.$e($paiement->reference ?: '—').'</td></tr>'
            .'<tr><td>Montant</td><td class="montant">'.number_format((float) $paiement->montant, 0, ',', ' ').' FCFA</td></tr>'
            .'</table><button onclick="window.print()">Imprimer ou enregistrer en PDF</button></body></html>';

        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    /** Journal des actions faites par la plateforme (création, suspension, renouvellement, support, annonces). */
    public function journal(Request $request): JsonResponse
    {
        $audits = \App\Models\Audit::query()
            ->with('user:id,name,email,role')
            ->whereHas('user', fn ($q) => $q->where('role', 'super_admin'))
            ->orderByDesc('created_at')
            ->paginate(min((int) $request->query('per_page', 25), 100));

        return response()->json($audits);
    }

    /** Export CSV de la liste des établissements (suivi commercial, sans données d'élèves). */
    public function exporterEtablissements(): \Symfony\Component\HttpFoundation\Response
    {
        $entetes = ['Établissement', 'Formule', 'Statut', 'Fin d\'abonnement', 'Élèves', 'Classes', 'Créé le'];
        $lignes = [];

        Etablissement::withCount(['classes', 'etudiants'])->orderBy('nom')->get()->each(function ($e) use (&$lignes) {
            $lignes[] = [
                $e->nom,
                config('plans.plans.'.$e->planCode().'.libelle'),
                $e->statut_abonnement,
                $e->abonnement_fin?->format('d/m/Y'),
                $e->etudiants_count,
                $e->classes_count,
                $e->created_at?->format('d/m/Y'),
            ];
        });

        $contenu = \App\Services\Xlsx::generer('Établissements', $entetes, $lignes, [], 'Plateforme — Liste des établissements');

        return response($contenu, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="etablissements.xlsx"',
        ]);
    }

    private function formatEtablissement(Etablissement $e): array
    {
        return [
            'id'                => $e->id,
            'nom'               => $e->nom,
            'responsable'       => $e->responsable,
            'telephone'         => $e->telephone,
            'adresse'           => $e->adresse,
            'annee_scolaire'    => $e->annee_scolaire,
            'plan'              => $e->plan ?? 'standard',
            'statut_abonnement' => $e->statut_abonnement ?? 'actif',
            'abonnement_fin'    => $e->abonnement_fin?->format('Y-m-d'),
            'prefixe_matricule' => $e->prefixe_matricule,
            'devise'            => $e->devise,
            'ministere'         => $e->ministere,
            'adresse'           => $e->adresse,
            'telephone'         => $e->telephone,
            'classes_count'     => $e->classes_count ?? 0,
            'etudiants_count'   => $e->etudiants_count ?? 0,
            'users_count'       => $e->users_count ?? 0,
            'created_at'        => $e->created_at?->format('d/m/Y'),
        ];
    }
}
