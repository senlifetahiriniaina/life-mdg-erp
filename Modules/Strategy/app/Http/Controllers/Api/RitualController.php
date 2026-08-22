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
        $tenantId = $this->tenantId($request);

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

        $tenantId = $this->tenantId($request);
        $ritual   = $this->service->createRitual($tenantId, $validated);

        return response()->json($ritual, 201);
    }

    /**
     * Chantier 32.27: `Route::apiResource('rituals', ...)` registers
     * `GET rituals/{ritual}` and `DELETE rituals/{ritual}` against show()/
     * destroy(), neither of which existed on this controller — confirmed
     * via reflection, a guaranteed fatal error on both real routes. Built
     * for real rather than restricting the route (a single-ritual detail
     * view and the ability to remove a stale/duplicate ritual are both
     * legitimate needs already implied by the rest of this CRUD).
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $ritual = $this->ritualInTenant($id, $this->tenantId($request));

        return response()->json($ritual->loadCount('sessions'));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'name'           => 'sometimes|string|max:255',
            'cadence'        => 'sometimes|in:weekly,biweekly,monthly,quarterly,annual',
            'attendee_roles' => 'nullable|array',
            'is_active'      => 'nullable|boolean',
        ]);

        $ritual = $this->ritualInTenant($id, $this->tenantId($request));
        $ritual->update($validated);

        return response()->json($ritual->fresh());
    }

    public function sessions(Request $request, int $id): JsonResponse
    {
        $this->ritualInTenant($id, $this->tenantId($request));

        $sessions = StrategyRitualSession::where('ritual_id', $id)
            ->orderBy('scheduled_at', 'desc')
            ->paginate(20);

        return response()->json($sessions);
    }

    public function createSession(Request $request, int $id): JsonResponse
    {
        $ritual  = $this->ritualInTenant($id, $this->tenantId($request));
        $session = $this->service->generateNextSession($ritual);

        return response()->json($session, 201);
    }

    public function startSession(Request $request, int $id): JsonResponse
    {
        $this->sessionInTenant($id, $this->tenantId($request));

        $session = $this->service->startSession($id);

        return response()->json($session);
    }

    public function completeSession(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'decisions'    => 'nullable|array',
            'action_items' => 'nullable|array',
        ]);

        $this->sessionInTenant($id, $this->tenantId($request));

        $session = $this->service->completeSession($id, $validated);

        return response()->json($session);
    }

    /**
     * Chantier 32.27 (audit 14 couches — layer 6, sécurité approfondie):
     * update()/sessions()/createSession()/startSession()/completeSession()
     * all resolved a route-bound StrategyRitual/StrategyRitualSession via
     * findOrFail() with zero tenant check anywhere — confirmed empirically
     * that any user of any company could read/mutate/complete another
     * company's real strategy ritual and its session decisions/action items
     * just by guessing the id. StrategyRitual carries a real tenant_id
     * column (unlike StrategyObjective) so no join through a parent record
     * is needed. No dedicated RitualPolicy exists (route-level role gate
     * only, matching the RateLimitController/AuthenticationEventController
     * "no natural per-ability model" precedent) — these two helpers are the
     * tenant-ownership check for this controller's mutating/reading actions.
     */
    private function ritualInTenant(int $ritualId, string $tenantId): StrategyRitual
    {
        $ritual = StrategyRitual::findOrFail($ritualId);

        abort_if((string) ($ritual->tenant_id ?? '') !== $tenantId, 404);

        return $ritual;
    }

    private function sessionInTenant(int $sessionId, string $tenantId): StrategyRitualSession
    {
        $session = StrategyRitualSession::with('ritual')->findOrFail($sessionId);

        abort_if((string) ($session->ritual?->tenant_id ?? '') !== $tenantId, 404);

        return $session;
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $ritual = $this->ritualInTenant($id, $this->tenantId($request));
        $ritual->delete();

        return response()->json(['message' => 'Ritual deleted.']);
    }

    public function upcoming(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $days     = $request->integer('days', 30);

        $sessions = $this->service->getUpcoming($tenantId, $days);

        return response()->json($sessions);
    }

    /**
     * Chantier 10 (Strategy): was $request->user()?->tenant_id ?? 'default' —
     * the phantom users.tenant_id column, never populated for real users, so
     * every tenant silently collapsed into one shared 'default' bucket (a
     * live cross-tenant leak). See StrategyPlanController::tenantId() for the
     * full rationale; fixed to the real company_id boundary column.
     */
    private function tenantId(Request $request): string
    {
        return (string) ($request->user()?->company_id ?? 0);
    }
}
