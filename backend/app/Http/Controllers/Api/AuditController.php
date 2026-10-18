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
        $this->middleware('role:admin,responsable_it');
    }

    public function index(Request $request): JsonResponse
    {
        $audits = Audit::query()
            ->with('user:id,name,role')
            ->when($request->query('module'), fn ($q, $v) => $q->where('module', $v))
            ->when($request->query('user_id'), fn ($q, $v) => $q->where('user_id', $v))
            ->orderByDesc('created_at')
            ->paginate((int) $request->query('per_page', 20));

        return response()->json($audits);
    }
}
