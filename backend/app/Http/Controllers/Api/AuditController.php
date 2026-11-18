<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin,proviseur,responsable_it,super_admin');
    }

    public function index(Request $request): JsonResponse
    {
        $utilisateur = $request->user();

        $audits = Audit::query()
            ->with('user:id,name,role')
            ->when(! $utilisateur->isSuperAdmin(), fn ($q) => $q->where('etablissement_id', $utilisateur->etablissement_id))
            ->when($request->query('module'), fn ($q, $v) => $q->where('module', $v))
            ->when($request->query('user_id'), fn ($q, $v) => $q->where('user_id', $v))
            ->when($request->query('entite_id'), fn ($q, $v) => $q->where('entite_id', $v))
            // Historique des modifications : seulement les entrées qui portent des valeurs avant ou après.
            ->when($request->boolean('modifications'), fn ($q) => $q->where(fn ($w) => $w->whereNotNull('valeurs_avant')->orWhereNotNull('valeurs_apres')))
            ->orderByDesc('created_at')
            ->paginate((int) $request->query('per_page', 20));

        return response()->json($audits);
    }
}
