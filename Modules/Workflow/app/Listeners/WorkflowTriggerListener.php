<?php

declare(strict_types=1);

namespace Modules\Workflow\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Modules\Workflow\Jobs\ExecuteWorkflowJob;

/**
 * WorkflowTriggerListener — Phase 39
 *
 * Listens for standard Laravel events fired by CRM, Sales, and Manufacturing
 * modules and dispatches ExecuteWorkflowJob with the appropriate trigger key.
 *
 * Register this listener in the Workflow module's ServiceProvider:
 *
 *   Event::listen('crm.opportunity.won',   WorkflowTriggerListener::class);
 *   Event::listen('sales.quote.approved',  WorkflowTriggerListener::class);
 *   Event::listen('sales.order.created',   WorkflowTriggerListener::class);
 *   Event::listen('sales.order.confirmed', WorkflowTriggerListener::class);
 *   Event::listen('manufacturing.bom.ready', WorkflowTriggerListener::class);
 *
 * Events must be fired like:
 *   event('crm.opportunity.won', ['opportunity_id' => 42, 'amount' => 150000, …])
 */
class WorkflowTriggerListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Map of fully-qualified event names → workflow trigger keys.
     * Supports both string events and object events (Eloquent model events).
     *
     * @var array<string,string>
     */
    protected array $triggerMap = [
        'crm.opportunity.won'       => 'crm.opportunity.won',
        'crm.opportunity.updated'   => 'crm.opportunity.updated',
        'sales.quote.approved'      => 'sales.quote.approved',
        'sales.quote.rejected'      => 'sales.quote.rejected',
        'sales.order.created'       => 'sales.order.created',
        'sales.order.confirmed'     => 'sales.order.confirmed',
        'sales.order.shipped'       => 'sales.order.shipped',
        'manufacturing.bom.ready'   => 'manufacturing.bom.ready',
        'manufacturing.order.done'  => 'manufacturing.order.done',
        'inventory.stock.low'       => 'inventory.stock.low',
    ];

    /**
     * Handle the incoming event.
     *
     * @param  string       $eventName  The fired event name
     * @param  array<mixed> $payload    Event payload (array with context data)
     */
    public function handle(string $eventName, array $payload): void
    {
        $triggerKey = $this->triggerMap[$eventName] ?? $eventName;
        $context    = $this->normalizeContext($payload);

        Log::debug('WorkflowTriggerListener: received event', [
            'event'       => $eventName,
            'trigger_key' => $triggerKey,
            'tenant_id'   => $context['tenant_id'] ?? null,
        ]);

        ExecuteWorkflowJob::dispatch($triggerKey, $context);
    }

    /**
     * Normalize the event payload into a flat context array.
     *
     * @param  array<mixed>  $payload
     * @return array<string,mixed>
     */
    private function normalizeContext(array $payload): array
    {
        // Laravel fires array events as [$eventName, [$data]]
        // The actual context is in the first element of the payload array
        $context = $payload[0] ?? $payload;

        if (is_object($context)) {
            // Eloquent model or value object — extract public properties
            return method_exists($context, 'toArray')
                ? $context->toArray()
                : (array) $context;
        }

        return is_array($context) ? $context : [];
    }
}
