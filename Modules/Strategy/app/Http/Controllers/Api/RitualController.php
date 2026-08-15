<?php

namespace Modules\Strategy\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Strategy\Models\StrategyRitual;
use Modules\Strategy\Models\StrategyRitualSession;
use Modules\Strategy\Services\RitualService;

class RitualController extends Controller
{
    public function __construct(private RitualService $service) {}

    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->header('X-Tenant-Id', $request->query('tenant_id', 'default'));

        $rituals = StrategyRitual::where('tenant_id', $tenantId)
            ->withCount('sessions')
            ->orderBy('name')
            ->paginate(20);

        return response()->json($rituals);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'type'           => 'required|in:weekly_checkin,monthly_review,quarterly_review,annual_planning',
            'cadence'        => 'required|in:weekly,biweekly,monthly,quarterly,annual',
            'day_of_week'    => 'nullable|integer|min:0|max:6',
            'day_of_month'   => 'nullable|integer|min:1|max:31',
            'attendee_roles' => 'nullable|array',
            'plan_id'        => 'nullable|exists:strategy_plans,id',
            'is_active'      => 'nullable|boolean',
        ]);

        $tenantId = $request->header('X-Tenant-Id', 'default');
        $ritual   = $this->service->createRitual($tenantId, $validated);

        return response()->json($ritual, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'name'           => 'sometimes|string|max:255',
            'cadence'        => 'sometimes|in:weekly,biweekly,monthly,quarterly,annual',
            'attendee_roles' => 'nullable|array',
            'is_active'      => 'nullable|boolean',
        ]);

        $ritual = StrategyRitual::findOrFail($id);
        $ritual->update($validated);

        return response()->json($ritual->fresh());
    }

    public function sessions(int $id): JsonResponse
    {
        $sessions = StrategyRitualSession::where('ritual_id', $id)
            ->orderBy('scheduled_at', 'desc')
            ->paginate(20);

        return response()->json($sessions);
    }

    public function createSession(Request $request, int $id): JsonResponse
    {
        $ritual  = StrategyRitual::findOrFail($id);
        $session = $this->service->generateNextSession($ritual);

        return response()->json($session, 201);
    }

    public function startSession(int $id): JsonResponse
    {
        $session = $this->service->startSession($id);

        return response()->json($session);
    }

    public function completeSession(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'decisions'    => 'nullable|array',
            'action_items' => 'nullable|array',
        ]);

        $session = $this->service->completeSession($id, $validated);

        return response()->json($session);
    }

    public function upcoming(Request $request): JsonResponse
    {
        $tenantId = $request->header('X-Tenant-Id', $request->query('tenant_id', 'default'));
        $days     = $request->integer('days', 30);

        $sessions = $this->service->getUpcoming($tenantId, $days);

        return response()->json($sessions);
    }
}
