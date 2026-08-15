<?php

declare(strict_types=1);

namespace Modules\AuditLog\Http\Controllers\Web;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Models\AuditLog;

class AuditLogWebController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'can:auditlog.logs.view-any']);
    }

    public function index(Request $request): Response
    {
        $query = AuditLog::query()
            ->with('user:id,name,email')
            ->orderByDesc('created_at');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('user_name', 'like', "%{$search}%")
                    ->orWhere('subject_type', 'like', "%{$search}%");
            });
        }

        if ($request->filled('module')) {
            $query->where('module', $request->input('module'));
        }

        if ($request->filled('event_type')) {
            $query->where('event_type', $request->input('event_type'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->input('date_to').' 23:59:59');
        }

        $logs = $query->paginate(50)->withQueryString();

        // Stats for the current filters (no pagination)
        $statsQuery = AuditLog::query();
        if ($request->filled('module')) {
            $statsQuery->where('module', $request->input('module'));
        }
        if ($request->filled('event_type')) {
            $statsQuery->where('event_type', $request->input('event_type'));
        }
        if ($request->filled('user_id')) {
            $statsQuery->where('user_id', $request->integer('user_id'));
        }
        if ($request->filled('date_from')) {
            $statsQuery->where('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $statsQuery->where('created_at', '<=', $request->input('date_to').' 23:59:59');
        }

        $today = AuditLog::where('created_at', '>=', now()->startOfDay())->count();

        $byModule = (clone $statsQuery)
            ->whereNotNull('module')
            ->selectRaw('module, COUNT(*) as count')
            ->groupBy('module')
            ->orderByDesc('count')
            ->pluck('count', 'module');

        $byEventType = (clone $statsQuery)
            ->selectRaw('event_type, COUNT(*) as count')
            ->groupBy('event_type')
            ->orderByDesc('count')
            ->pluck('count', 'event_type');

        $modules = AuditLog::whereNotNull('module')->distinct()->orderBy('module')->pluck('module');
        $eventTypes = AuditLog::whereNotNull('event_type')->distinct()->orderBy('event_type')->pluck('event_type');

        return Inertia::render('AuditLog/Index', [
            'logs' => $logs,
            'stats' => [
                'today' => $today,
                'total' => $logs->total(),
                'by_module' => $byModule,
                'by_event_type' => $byEventType,
            ],
            'modules' => $modules,
            'eventTypes' => $eventTypes,
            'filters' => $request->only(['search', 'module', 'event_type', 'user_id', 'date_from', 'date_to']),
        ]);
    }
}
