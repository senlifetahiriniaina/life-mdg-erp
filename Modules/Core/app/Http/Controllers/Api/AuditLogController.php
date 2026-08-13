<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Core\Models\AuditLog;
use Modules\Core\Services\AuditService;

/**
 * @group Core - Audit Logs
 *
 * Read-only access to the immutable audit trail.
 */
class AuditLogController extends Controller
{
    public function __construct(private readonly AuditService $service)
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * List audit logs with optional filters.
     *
     * GET /api/v1/core/audit-logs
     */
    public function index(Request $request): JsonResponse
    {
        $query = AuditLog::query()->orderByDesc('created_at');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }

        if ($request->filled('module')) {
            $query->where('module', $request->input('module'));
        }

        if ($request->filled('event_type')) {
            $query->where('event_type', $request->input('event_type'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('user_name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('subject_type')) {
            $query->where('subject_type', $request->input('subject_type'));
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->input('date_to'));
        }

        return response()->json($query->paginate(50));
    }

    /**
     * Return aggregate statistics.
     *
     * GET /api/v1/core/audit-logs/stats
     */
    public function stats(Request $request): JsonResponse
    {
        $filters = $request->only(['user_id', 'date_from', 'date_to']);
        $stats = $this->service->getStats(array_filter($filters, fn ($v) => $v !== null && $v !== ''));

        return response()->json($stats);
    }

    /**
     * Show a single audit log entry.
     *
     * GET /api/v1/core/audit-logs/{auditLog}
     */
    public function show(AuditLog $auditLog): JsonResponse
    {
        return response()->json($auditLog->load('user'));
    }

    /**
     * Return activity for a specific user.
     *
     * GET /api/v1/core/audit-logs/user/{userId}
     */
    public function userActivity(int $userId): JsonResponse
    {
        $logs = $this->service->getUserActivity($userId);

        return response()->json(['data' => $logs]);
    }

    /**
     * Return history for a specific subject (model).
     *
     * GET /api/v1/core/audit-logs/subject/{type}/{id}
     */
    public function subjectHistory(string $type, int $id): JsonResponse
    {
        $logs = $this->service->getSubjectHistory($type, $id);

        return response()->json(['data' => $logs]);
    }
}
