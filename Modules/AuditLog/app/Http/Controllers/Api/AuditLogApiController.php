<?php

declare(strict_types=1);

namespace Modules\AuditLog\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Models\AuditLog;

/**
 * @group AuditLog API
 *
 * REST API for browsing and exporting audit log entries.
 */
class AuditLogApiController extends Controller
{
    /**
     * List audit log entries (paginated, 50 per page).
     *
     * Supported filters: search, module, event_type, user_id, date_from, date_to
     */
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('auditlog.logs.view'), 403);

        $validated = $request->validate([
            'search'     => ['nullable', 'string', 'max:255'],
            'module'     => ['nullable', 'string', 'max:100'],
            'event_type' => ['nullable', 'string', 'max:100'],
            'user_id'    => ['nullable', 'integer', 'min:1'],
            'date_from'  => ['nullable', 'date'],
            'date_to'    => ['nullable', 'date'],
            'per_page'   => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $query = AuditLog::query()
            ->with('user:id,name,email')
            ->orderByDesc('created_at');

        $this->applyFilters($query, $validated);

        $logs = $query->paginate($validated['per_page'] ?? 50);

        return response()->json($logs);
    }

    /**
     * Show a single audit log entry.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()->can('auditlog.logs.view'), 403);

        $log = AuditLog::with('user:id,name,email')->find($id);

        if ($log === null) {
            return response()->json(['message' => 'Audit log entry not found'], 404);
        }

        return response()->json($log);
    }

    /**
     * Return aggregate statistics for audit logs.
     *
     * Returns: today, this_week, this_month counts, top 10 by module, top 10 by event_type.
     */
    public function stats(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('auditlog.logs.view'), 403);

        $today     = AuditLog::where('created_at', '>=', now()->startOfDay())->count();
        $thisWeek  = AuditLog::where('created_at', '>=', now()->startOfWeek())->count();
        $thisMonth = AuditLog::where('created_at', '>=', now()->startOfMonth())->count();

        $byModule = AuditLog::whereNotNull('module')
            ->selectRaw('module, COUNT(*) as count')
            ->groupBy('module')
            ->orderByDesc('count')
            ->limit(10)
            ->pluck('count', 'module');

        $byEventType = AuditLog::whereNotNull('event_type')
            ->selectRaw('event_type, COUNT(*) as count')
            ->groupBy('event_type')
            ->orderByDesc('count')
            ->limit(10)
            ->pluck('count', 'event_type');

        return response()->json([
            'today'        => $today,
            'this_week'    => $thisWeek,
            'this_month'   => $thisMonth,
            'by_module'    => $byModule,
            'by_event_type'=> $byEventType,
        ]);
    }

    /**
     * Export audit logs as JSON (same filters as index, max 10 000 records).
     */
    public function export(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('auditlog.logs.export'), 403);

        $validated = $request->validate([
            'search'     => ['nullable', 'string', 'max:255'],
            'module'     => ['nullable', 'string', 'max:100'],
            'event_type' => ['nullable', 'string', 'max:100'],
            'user_id'    => ['nullable', 'integer', 'min:1'],
            'date_from'  => ['nullable', 'date'],
            'date_to'    => ['nullable', 'date'],
        ]);

        $query = AuditLog::query()
            ->with('user:id,name,email')
            ->orderByDesc('created_at');

        $this->applyFilters($query, $validated);

        $logs = $query->limit(10000)->get();

        return response()->json([
            'count' => $logs->count(),
            'data'  => $logs,
        ]);
    }

    // ─── Private Helpers ──────────────────────────────────────────────────────

    /**
     * Apply shared filter conditions to a query builder.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array<string, mixed>                  $filters
     */
    private function applyFilters(\Illuminate\Database\Eloquent\Builder $query, array $filters): void
    {
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('user_name', 'like', "%{$search}%")
                  ->orWhere('subject_type', 'like', "%{$search}%")
                  ->orWhere('action', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['module'])) {
            $query->where('module', $filters['module']);
        }

        if (!empty($filters['event_type'])) {
            $query->where('event_type', $filters['event_type']);
        }

        if (!empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }

        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to'] . ' 23:59:59');
        }
    }
}
