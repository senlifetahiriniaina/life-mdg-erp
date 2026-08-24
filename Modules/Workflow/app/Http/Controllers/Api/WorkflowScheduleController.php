<?php

declare(strict_types=1);

namespace Modules\Workflow\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
// Chantier 19 Lot 3: was `use Modules\Workflow\Models\AutomationFlow;` — that
// class does not exist anywhere in the repo (confirmed via `class_exists()`)
// — the real model lives one namespace level deeper, at
// Modules\Workflow\Models\Automation\AutomationFlow, and is what
// AutomationFlowController/FlowExecutionEngine/AutomationFlowTemplate all
// actually use. Every method on this controller (index/show/update/enable/
// disable, either via the AutomationFlow::query() call or the AutomationFlow
// $flow route-model-binding type-hint) was a guaranteed fatal
// "Class not found" error the moment it was ever hit — currently dormant
// only because this controller has zero routes registered anywhere (see
// the docblock on the unrouted-controllers note below), not because the
// bug isn't real.
use Modules\Workflow\Models\Automation\AutomationFlow;
use Modules\Workflow\Services\Automation\FlowSchedulerService;

/**
 * @group Workflow - Schedules
 *
 * Manage cron-based automation schedules.
 *
 * Chantier 32.11: actually exercising this controller via a real HTTP
 * request (not just reading the code) surfaced a second bug underneath the
 * Chantier 19 Lot 3 class-not-found fix — every method here read/wrote
 * `$flow->schedule_config`, a column that has never existed on
 * `automation_flows` at all (confirmed via `Schema::getColumnListing()`)
 * and isn't even in `AutomationFlow::$fillable`. The real, live scheduler
 * (FlowSchedulerService::schedule()/unschedule()/getDueFlows(), already
 * used elsewhere by this exact class) has only ever stored the cron
 * expression at `trigger_config['cron']` — a real, existing, already-
 * fillable/cast column. This controller's own `update()` was therefore
 * silently a no-op on every real call (Eloquent mass-assignment silently
 * drops a key absent from $fillable), and enable()/disable()/due() would
 * genuinely schedule/unschedule via FlowSchedulerService but this
 * controller's own index()/show() could never see it, since they read the
 * phantom field back. Fixed to use `trigger_config['cron']` throughout,
 * matching FlowSchedulerService::getCronExpression()'s own real read path
 * exactly.
 */
class WorkflowScheduleController extends Controller
{
    public function __construct(private FlowSchedulerService $scheduler) {}

    /**
     * List all flows that have a schedule configured.
     */
    public function index(Request $request): JsonResponse
    {
        // Chantier 10: was $request->user()?->tenant_id ?? 1 — the
        // well-documented phantom column, collapsing every tenant's
        // scheduled flows into one shared bucket. Fixed to the real tenant
        // boundary column, company_id (automation_flows.tenant_id is a real
        // integer column, unlike Security/Integration/Secrets' string(36)
        // leftover — no cast needed here).
        $tenantId = $this->resolveTenantId($request);

        $flows = AutomationFlow::query()
            ->where('tenant_id', $tenantId)
            ->where('trigger_type', 'schedule')
            ->get()
            ->filter(fn (AutomationFlow $flow) => isset($flow->trigger_config['cron']))
            ->map(function (AutomationFlow $flow) {
                $cronExpr = $flow->trigger_config['cron'] ?? null;
                return [
                    'flow_id'        => $flow->id,
                    'flow_name'      => $flow->name,
                    'is_active'      => $flow->is_active,
                    'cron'           => $cronExpr,
                    'next_run_at'    => $cronExpr ? $this->scheduler->getNextRunTime($cronExpr)->toIso8601String() : null,
                    'trigger_config' => $flow->trigger_config,
                ];
            })
            ->values();

        return response()->json(['data' => $flows]);
    }

    /**
     * Chantier 32.11: show()/update()/enable()/disable() below all took a
     * route-bound AutomationFlow with zero ownership check — since this
     * controller has zero routes registered anywhere (dormant, not live),
     * this was a dormant cross-tenant IDOR rather than an exploited one,
     * matching the same "close the landmine before wiring it up" precedent
     * already established this session (Chantier 19 Lot 3's own docblock
     * fix on this exact controller). Fixed now, before this chantier
     * actually routes it for the first time.
     */
    private function assertOwnership(Request $request, AutomationFlow $flow): void
    {
        abort_unless($flow->tenant_id === $this->resolveTenantId($request), 404);
    }

    private function resolveTenantId(Request $request): int
    {
        return (int) ($request->user()?->company_id ?? 0);
    }

    /**
     * Get schedule details and next run time for a flow.
     */
    public function show(Request $request, AutomationFlow $flow): JsonResponse
    {
        $this->assertOwnership($request, $flow);

        $cronExpr = $flow->trigger_config['cron'] ?? null;

        return response()->json([
            'data' => [
                'flow_id'        => $flow->id,
                'flow_name'      => $flow->name,
                'is_active'      => $flow->is_active,
                'cron'           => $cronExpr,
                'next_run_at'    => $cronExpr ? $this->scheduler->getNextRunTime($cronExpr)->toIso8601String() : null,
                'trigger_config' => $flow->trigger_config,
            ],
        ]);
    }

    /**
     * Set or update the schedule for a flow.
     *
     * @bodyParam cron string required Cron expression. Example: 0 9 * * 1
     * @bodyParam timezone string Timezone. Example: Africa/Dakar
     */
    public function update(Request $request, AutomationFlow $flow): JsonResponse
    {
        $this->assertOwnership($request, $flow);

        $validated = $request->validate([
            'cron'     => 'required|string',
            'timezone' => 'sometimes|string|timezone',
        ]);

        $flow->update([
            'trigger_type'   => 'schedule',
            'trigger_config' => array_merge($flow->trigger_config ?? [], $validated),
        ]);

        $this->scheduler->schedule($flow->fresh());

        return response()->json(['message' => 'Schedule updated', 'data' => $flow->fresh()]);
    }

    /**
     * Enable scheduling for a flow.
     */
    public function enable(Request $request, AutomationFlow $flow): JsonResponse
    {
        $this->assertOwnership($request, $flow);

        $this->scheduler->schedule($flow);

        return response()->json(['message' => 'Schedule enabled']);
    }

    /**
     * Disable scheduling for a flow.
     */
    public function disable(Request $request, AutomationFlow $flow): JsonResponse
    {
        $this->assertOwnership($request, $flow);

        $this->scheduler->unschedule($flow);

        return response()->json(['message' => 'Schedule disabled']);
    }

    /**
     * List flows that are due for execution right now.
     *
     * Chantier 32.11: FlowSchedulerService::getDueFlows() is deliberately
     * cross-tenant (the real cron dispatcher needs to see every tenant's
     * due flows to actually run them) — but this HTTP endpoint sits behind
     * the module's ordinary role:manager,admin gate, not a super-admin-only
     * one, so exposing the service's raw output here would leak every
     * other tenant's flow names/schedules to any manager. Scoped to the
     * caller's own tenant, matching every other method on this controller.
     */
    public function due(Request $request): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request);
        $flows    = $this->scheduler->getDueFlows()
            ->where('tenant_id', $tenantId)
            ->values();

        return response()->json(['data' => $flows]);
    }
}
