<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Enseignant;
use Illuminate\Http\Request;
use App\Services\AuditLogger;

class EnseignantController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('role:admin,proviseur')->except(['index']);
    }

    public function index(Request $request)
    {
        $query = Enseignant::query();

        if (! $request->user()->hasAnyRole(['admin', 'proviseur', 'censeur'])) {
            $query->select(['id', 'nom', 'prenom', 'specialite', 'etablissement_id']);
        }

        if ($request->matiere_id) {
            $query->whereHas('matieres', fn($q) => $q->where('matiere_id', $request->matiere_id));
        }

        if ($request->search) {
            $search = $request->search;
            $query->where(fn ($q) => $q->where('nom', 'like', "%{$search}%")
                ->orWhere('prenom', 'like', "%{$search}%"));
        }

        return response()->json($query->paginate(20));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom' => 'required|string',
            'prenom' => 'required|string',
            'email' => 'required|email|unique:enseignants',
            'telephone' => 'nullable|string',
            'specialite' => 'nullable|string',
        ]);

        $enseignant = Enseignant::create($validated);

        AuditLogger::log(auth()->user(), 'create', 'Enseignant', $enseignant->id, "Nouvel enseignant : {$enseignant->nom}");

        return response()->json($enseignant, 201);
    }

    public function show(Enseignant $enseignant)
    {
        return response()->json($enseignant->load(['matieres', 'classes']));
    }

    public function update(Request $request, Enseignant $enseignant)
    {
        $validated = $request->validate([
            'nom' => 'string',
            'prenom' => 'string',
            'email' => "email|unique:enseignants,email,{$enseignant->id}",
            'telephone' => 'nullable|string',
            'specialite' => 'nullable|string',
        ]);

        $enseignant->update($validated);

        AuditLogger::log(auth()->user(), 'update', 'Enseignant', $enseignant->id, "Mise à jour de l'enseignant");

        return response()->json($enseignant);
    }

    public function destroy(Enseignant $enseignant)
    {
        AuditLogger::log(auth()->user(), 'delete', 'Enseignant', $enseignant->id, "Suppression de l'enseignant");

        $enseignant->delete();

        return response()->json(null, 204);
    }
}
