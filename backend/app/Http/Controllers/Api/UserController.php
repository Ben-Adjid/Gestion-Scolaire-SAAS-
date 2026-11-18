<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin,proviseur');
    }

    /** Un proviseur ne gère pas les comptes de direction ni de plateforme. */
    private function assertPeutGererCompte(Request $request, User $cible): void
    {
        if ($request->user()->role === 'proviseur' && in_array($cible->role, ['super_admin', 'admin', 'proviseur'], true)) {
            abort(403, "Un proviseur ne peut pas modifier un compte de direction.");
        }
    }

    public function index(Request $request): JsonResponse
    {
        $users = User::query()
            ->with(['enseignant:id,nom,prenom', 'permissions:id,code'])
            ->when($request->query('search'), function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->paginate((int) $request->query('per_page', 15));

        return response()->json($users);
    }

    public function store(UserRequest $request): JsonResponse
    {
        // Limite d'utilisateurs selon la formule (illimité pour Entreprise).
        $tenant = $request->user()->etablissement;
        $max = $tenant?->maxUtilisateurs();
        if ($max !== null && User::where('etablissement_id', $tenant->id)->count() >= $max) {
            return response()->json([
                'message' => "Limite de {$max} utilisateurs atteinte pour votre formule. Passez à une formule supérieure pour en ajouter.",
                'code' => 'limite_utilisateurs',
            ], 403);
        }

        $data = $request->validated();
        $data['password'] = Hash::make($data['password']);
        $this->verifierFormulePermissions($request, $data['permissions'] ?? []);
        $codesPermissions = $data['permissions'] ?? [];
        unset($data['permissions']);

        $user = User::create($data);
        // Le mot de passe choisi par l'administrateur est temporaire : l'utilisateur le changera à sa première connexion.
        $user->forceFill(['doit_changer_mot_de_passe' => true])->save();
        $this->synchroniserPermissions($user, $codesPermissions);

        AuditLogger::log($request->user(), 'creation', 'utilisateurs', $user->id, "Création du compte {$user->name} ({$user->role}).");

        // Bienvenue par e-mail : un échec d'envoi ne bloque jamais la création du compte.
        try {
            \Illuminate\Support\Facades\Mail::to($user->email)->send(new \App\Mail\CompteCree(
                $user->name,
                ucfirst(str_replace('_', ' ', $user->role)),
                $user->etablissement?->nom ?? 'votre établissement',
            ));
        } catch (\Throwable $e) {
            // Le compte est créé même si l'e-mail n'a pas pu partir.
        }

        return response()->json([
            'message' => 'Utilisateur créé avec succès.',
            'user' => $user->load('enseignant:id,nom,prenom'),
        ], 201);
    }

    /** Catalogue des permissions, regroupées par module, pour construire le formulaire. */
    public function catalogue(): JsonResponse
    {
        $modules = \App\Models\Permission::orderBy('module')->orderBy('id')->get()
            ->groupBy('module')
            ->map(fn ($permissions, $module) => [
                'module' => $module,
                'permissions' => $permissions->map(fn ($p) => ['code' => $p->code, 'libelle' => $p->libelle])->values(),
            ])
            ->values();

        return response()->json($modules);
    }

    /**
     * Les permissions granulaires sont réservées à la formule VIP et plus.
     * En Standard, les rôles sont fixes : toute tentative d'accorder une permission est refusée.
     */
    private function verifierFormulePermissions(Request $request, array $codes): void
    {
        if ($codes === []) {
            return;
        }

        $tenant = $request->user()->etablissement;
        if ($tenant && ! in_array('permissions_granulaires', $tenant->fonctionnalites(), true)) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(response()->json([
                'message' => 'Les permissions personnalisées sont incluses dans la formule VIP.',
                'code' => 'plan_insuffisant',
            ], 403));
        }
    }

    /** Remplace les permissions accordées à un compte par la liste donnée (codes du catalogue). */
    private function synchroniserPermissions(User $user, array $codes): void
    {
        $ids = \App\Models\Permission::whereIn('code', $codes)->pluck('id');
        $user->permissions()->sync($ids);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json($user->load(['enseignant:id,nom,prenom', 'permissions:id,code']));
    }

    public function update(UserRequest $request, User $user): JsonResponse
    {
        $this->assertPeutGererCompte($request, $user);
        $data = $request->validated();

        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $this->verifierFormulePermissions($request, $data['permissions'] ?? []);

        if (array_key_exists('permissions', $data)) {
            $this->synchroniserPermissions($user, $data['permissions'] ?? []);
        }
        unset($data['permissions']);

        $user->update($data);

        return response()->json([
            'message' => 'Utilisateur mis à jour avec succès.',
            'user' => $user->load('enseignant:id,nom,prenom'),
        ]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->assertPeutGererCompte($request, $user);
        if ($user->id === $request->user()->id) {
            return response()->json([
                'message' => 'Vous ne pouvez pas supprimer votre propre compte.',
            ], 422);
        }

        $user->delete();

        AuditLogger::log($request->user(), 'suppression', 'utilisateurs', $user->id, "Suppression du compte {$user->name}.");

        return response()->json(['message' => 'Utilisateur supprimé avec succès.']);
    }
}
