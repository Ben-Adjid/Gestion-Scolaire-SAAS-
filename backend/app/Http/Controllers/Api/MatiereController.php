<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Matiere;
use Illuminate\Http\Request;
use App\Services\AuditLogger;
use App\Services\TeacherScope;

class MatiereController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('role:admin,censeur,proviseur')->except(['index', 'show']);
    }

    public function index(Request $request)
    {
        $query = Matiere::query();

        $matiereIds = TeacherScope::matiereIds($request->user());
        if ($matiereIds !== null) {
            $query->whereIn('id', $matiereIds);
        }

        if ($request->search) {
            $search = $request->search;
            $query->where(fn ($q) => $q->where('nom', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%"));
        }

        return response()->json($query->paginate(20));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom' => 'required|string|unique:matieres',
            'code' => 'required|string|unique:matieres',
            'coefficient' => 'required|numeric|min:1|max:5',
            'couleur' => 'nullable|string|regex:/^#[0-9A-F]{6}$/i',
        ]);

        $matiere = Matiere::create($validated);

        AuditLogger::log(auth()->user(), 'create', 'Matiere', $matiere->id, "Nouvelle matière : {$matiere->nom}");

        return response()->json($matiere, 201);
    }

    public function show(Matiere $matiere)
    {
        return response()->json($matiere->load(['enseignants', 'notes']));
    }

    public function update(Request $request, Matiere $matiere)
    {
        $validated = $request->validate([
            'nom' => "string|unique:matieres,nom,{$matiere->id}",
            'code' => "string|unique:matieres,code,{$matiere->id}",
            'coefficient' => 'numeric|min:1|max:5',
            'couleur' => 'nullable|string|regex:/^#[0-9A-F]{6}$/i',
        ]);

        $matiere->update($validated);

        AuditLogger::log(auth()->user(), 'update', 'Matiere', $matiere->id, "Mise à jour de la matière");

        return response()->json($matiere);
    }

    public function destroy(Matiere $matiere)
    {
        AuditLogger::log(auth()->user(), 'delete', 'Matiere', $matiere->id, "Suppression de la matière");

        $matiere->delete();

        return response()->json(null, 204);
    }
}
