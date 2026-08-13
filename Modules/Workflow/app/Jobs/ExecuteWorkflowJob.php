<?php

declare(strict_types=1);

namespace Modules\Workflow\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Workflow\Services\WorkflowEngineService;

/**
 * ExecuteWorkflowJob — Phase 39
 *
 * Dispatched by WorkflowTriggerListener when a module fires a workflow trigger event.
 * Runs asynchronously on the 'workflows' queue to avoid blocking request processing.
 */
class ExecuteWorkflowJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Timeout in seconds.
     */
    public int $timeout = 120;

    /**
     * @param  string               $triggerKey  e.g. 'crm.opportunity.won'
     * @param  array<string,mixed>  $context     Trigger payload
     */
    public function __construct(
        public readonly string $triggerKey,
        public readonly array  $context,
    ) {
        $this->onQueue('workflows');
    }

    /**
     * Execute all matching workflow definitions for the given trigger.
     */
    public function handle(WorkflowEngineService $engine): void
    {
        Log::info('ExecuteWorkflowJob: processing trigger', [
            'trigger_key' => $this->triggerKey,
            'tenant_id'   => $this->context['tenant_id'] ?? null,
        ]);

        $results = $engine->executeWorkflow($this->triggerKey, $this->context);

        $succeeded = collect($results)->where('status', 'completed')->count();
        $failed    = collect($results)->where('status', 'failed')->count();

        Log::info('ExecuteWorkflowJob: finished', [
            'trigger_key' => $this->triggerKey,
            'total'       => count($results),
            'succeeded'   => $succeeded,
            'failed'      => $failed,
        ]);
    }

    /**
     * Handle job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ExecuteWorkflowJob: job failed', [
            'trigger_key' => $this->triggerKey,
            'error'       => $exception->getMessage(),
        ]);
    }
}
