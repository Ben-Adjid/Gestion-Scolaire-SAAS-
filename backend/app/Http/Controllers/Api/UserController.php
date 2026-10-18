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
        $this->middleware('role:admin');
    }

    public function index(Request $request): JsonResponse
    {
        $users = User::query()
            ->with('enseignant:id,nom,prenom')
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
        $data = $request->validated();
        $data['password'] = Hash::make($data['password']);

        $user = User::create($data);

        AuditLogger::log($request->user(), 'creation', 'utilisateurs', $user->id, "Création du compte {$user->name} ({$user->role}).");

        return response()->json([
            'message' => 'Utilisateur créé avec succès.',
            'user' => $user->load('enseignant:id,nom,prenom'),
        ], 201);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json($user->load('enseignant:id,nom,prenom'));
    }

    public function update(UserRequest $request, User $user): JsonResponse
    {
        $data = $request->validated();

        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        return response()->json([
            'message' => 'Utilisateur mis à jour avec succès.',
            'user' => $user->load('enseignant:id,nom,prenom'),
        ]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
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
