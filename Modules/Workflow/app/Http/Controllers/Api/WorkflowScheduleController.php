<?php

declare(strict_types=1);

namespace Modules\Workflow\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Workflow\Models\AutomationFlow;
use Modules\Workflow\Services\Automation\FlowSchedulerService;

/**
 * @group Workflow - Schedules
 *
 * Manage cron-based automation schedules.
 */
class WorkflowScheduleController extends Controller
{
    public function __construct(private FlowSchedulerService $scheduler) {}

    /**
     * List all flows that have a schedule configured.
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->user()?->tenant_id ?? 1;

        $flows = AutomationFlow::query()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('schedule_config')
            ->get()
            ->map(function (AutomationFlow $flow) {
                $cronExpr = $flow->schedule_config['cron'] ?? null;
                return [
                    'flow_id'     => $flow->id,
                    'flow_name'   => $flow->name,
                    'is_active'   => $flow->is_active,
                    'cron'        => $cronExpr,
                    'next_run_at' => $cronExpr ? $this->scheduler->getNextRunTime($cronExpr)->toIso8601String() : null,
                    'schedule_config' => $flow->schedule_config,
                ];
            });

        return response()->json(['data' => $flows]);
    }

    /**
     * Get schedule details and next run time for a flow.
     */
    public function show(Request $request, AutomationFlow $flow): JsonResponse
    {
        $cronExpr = $flow->schedule_config['cron'] ?? null;

        return response()->json([
            'data' => [
                'flow_id'     => $flow->id,
                'flow_name'   => $flow->name,
                'is_active'   => $flow->is_active,
                'cron'        => $cronExpr,
                'next_run_at' => $cronExpr ? $this->scheduler->getNextRunTime($cronExpr)->toIso8601String() : null,
                'schedule_config' => $flow->schedule_config,
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
        $validated = $request->validate([
            'cron'     => 'required|string',
            'timezone' => 'sometimes|string|timezone',
        ]);

        $flow->update([
            'schedule_config' => array_merge($flow->schedule_config ?? [], $validated),
        ]);

        $this->scheduler->schedule($flow->fresh());

        return response()->json(['message' => 'Schedule updated', 'data' => $flow->fresh()]);
    }

    /**
     * Enable scheduling for a flow.
     */
    public function enable(AutomationFlow $flow): JsonResponse
    {
        $this->scheduler->schedule($flow);

        return response()->json(['message' => 'Schedule enabled']);
    }

    /**
     * Disable scheduling for a flow.
     */
    public function disable(AutomationFlow $flow): JsonResponse
    {
        $this->scheduler->unschedule($flow);

        return response()->json(['message' => 'Schedule disabled']);
    }

    /**
     * List flows that are due for execution right now.
     */
    public function due(): JsonResponse
    {
        $flows = $this->scheduler->getDueFlows();

        return response()->json(['data' => $flows]);
    }
}
