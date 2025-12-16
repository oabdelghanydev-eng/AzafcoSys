<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags AuditLog
 */
class AuditLogController extends Controller
{
    use ApiResponse;

    /**
     * List audit logs with filters
     * Permission: admin only
     */
    public function index(Request $request): JsonResponse
    {
        $this->ensureAdmin();

        $logs = AuditLog::with('user:id,name')
            ->when($request->model_type, fn ($q, $t) => $q->where('model_type', 'like', "%{$t}%"))
            ->when($request->model_id, fn ($q, $id) => $q->where('model_id', $id))
            ->when($request->action, fn ($q, $a) => $q->where('action', $a))
            ->when($request->user_id, fn ($q, $id) => $q->where('user_id', $id))
            ->when($request->date_from, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->date_to, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 50);

        return $this->success($logs);
    }

    /**
     * Get audit trail for specific model
     * Permission: admin only
     */
    public function trail(Request $request): JsonResponse
    {
        $this->ensureAdmin();

        $request->validate([
            'model_type' => 'required|string',
            'model_id' => 'required|integer',
        ]);

        $logs = AuditLog::where('model_type', 'like', '%'.$request->model_type.'%')
            ->where('model_id', $request->model_id)
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->get();

        return $this->success($logs);
    }
}
