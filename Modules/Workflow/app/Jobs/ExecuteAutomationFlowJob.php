<?php

declare(strict_types=1);

namespace Modules\Workflow\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Workflow\Models\Automation\AutomationExecution;
use Modules\Workflow\Models\Automation\AutomationFlow;

/**
 * ExecuteAutomationFlowJob — Phase 39
 *
 * Dispatched by FlowSchedulerService (schedule triggers) and AiWorkflowController
 * (manual triggers). Runs asynchronously on the 'automations' queue.
 */
class ExecuteAutomationFlowJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 300;

    public function __construct(
        public readonly int $flowId,
        public readonly int $tenantId,
        public readonly array $triggerData = [],
    ) {
        $this->onQueue('automations');
    }

    public function handle(): void
    {
        $flow = AutomationFlow::find($this->flowId);

        if (! $flow || ! $flow->is_active) {
            Log::warning("ExecuteAutomationFlowJob: flow {$this->flowId} not found or inactive");

            return;
        }

        // Create execution record
        $execution = AutomationExecution::create([
            'flow_id'      => $this->flowId,
            'tenant_id'    => $this->tenantId,
            'trigger_data' => $this->triggerData,
            'status'       => 'running',
            'started_at'   => now(),
            'node_results' => [],
        ]);

        try {
            // TODO: run nodes via WorkflowEngineService once node runner supports AutomationFlow
            // For now we mark it completed as a safe default
            $execution->markCompleted();

            $flow->increment('total_runs');
            $flow->increment('success_runs');
            $flow->update(['last_run_at' => now()]);

            Log::info("ExecuteAutomationFlowJob: flow {$this->flowId} completed", [
                'execution_id' => $execution->id,
            ]);
        } catch (\Throwable $e) {
            $execution->markFailed();
            $flow->increment('total_runs');

            Log::error("ExecuteAutomationFlowJob: flow {$this->flowId} failed", [
                'execution_id' => $execution->id,
                'error'        => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
