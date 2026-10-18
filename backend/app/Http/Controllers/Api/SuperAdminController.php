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
            ->map(fn ($e) => $this->formatEtablissement($e));

        return response()->json(['data' => $etablissements]);
    }

    /** Détail d'un établissement. */
    public function showEtablissement(Etablissement $etablissement): JsonResponse
    {
        $etablissement->loadCount(['classes', 'etudiants', 'users']);
        return response()->json($this->formatEtablissement($etablissement));
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
            'plan'             => ['required', Rule::in(['starter', 'standard', 'premium', 'entreprise'])],
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
            ]);

            return [$etablissement];
        });

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
            'plan'              => ['nullable', Rule::in(['starter', 'standard', 'premium', 'entreprise'])],
            'statut_abonnement' => ['nullable', Rule::in(['actif', 'suspendu', 'expire', 'essai'])],
            'abonnement_fin'    => 'nullable|date',
        ]);

        $etablissement->update($data);

        return response()->json([
            'message'       => 'Établissement mis à jour.',
            'etablissement' => $this->formatEtablissement($etablissement->loadCount(['classes', 'etudiants', 'users'])),
        ]);
    }

    /** Suspendre un établissement (bloque la connexion de ses utilisateurs). */
    public function suspendreEtablissement(Etablissement $etablissement): JsonResponse
    {
        $etablissement->update(['statut_abonnement' => 'suspendu']);
        return response()->json(['message' => "Établissement \"{$etablissement->nom}\" suspendu."]);
    }

    /** Réactiver un établissement suspendu. */
    public function reactiverEtablissement(Etablissement $etablissement): JsonResponse
    {
        $etablissement->update(['statut_abonnement' => 'actif']);
        return response()->json(['message' => "Établissement \"{$etablissement->nom}\" réactivé."]);
    }

    /** Supprimer un établissement et toutes ses données. */
    public function destroyEtablissement(Etablissement $etablissement): JsonResponse
    {
        $nom = $etablissement->nom;
        $etablissement->delete();
        return response()->json(['message' => "Établissement \"{$nom}\" supprimé."]);
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

        return response()->json([
            'stats'   => $stats,
            'recents' => $recents,
        ]);
    }

    // ──────────────────────────────────────────────
    // HELPERS
    // ──────────────────────────────────────────────

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
            'abonnement_fin'    => $e->abonnement_fin,
            'classes_count'     => $e->classes_count ?? 0,
            'etudiants_count'   => $e->etudiants_count ?? 0,
            'users_count'       => $e->users_count ?? 0,
            'created_at'        => $e->created_at?->format('d/m/Y'),
        ];
    }
}
