<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Services;

use Illuminate\Database\Eloquent\Model;
use Modules\Helpdesk\Models\SlaPolicy;
use Modules\Helpdesk\Models\Ticket;

/**
 * Central entry point for creating tickets from any other module in the
 * system (the `HelpdeskLinkable` trait and the generic
 * POST /api/v1/helpdesk/tickets endpoint both go through here), so every
 * caller gets the same SLA assignment and ticket-number generation the
 * dedicated TicketController already applies, instead of each integration
 * re-implementing ticket creation (or writing raw SQL against the wrong
 * schema, as the previous Workflow action handler did).
 */
class TicketService
{
    public function __construct(private readonly SlaService $slaService) {}

    /**
     * Create a ticket, optionally linked to a source record from any module
     * via the polymorphic source_type/source_id columns.
     *
     * @param  array<string,mixed>  $data
     */
    public function createFromSource(?Model $source, array $data): Ticket
    {
        $attributes = array_filter($data, fn ($value) => $value !== null && $value !== '');
        $attributes['status'] = $attributes['status'] ?? 'open';
        $attributes['priority'] = $attributes['priority'] ?? 'medium';

        if ($source) {
            $attributes['source_type'] = $source::class;
            $attributes['source_id'] = $source->getKey();
        }

        $ticket = Ticket::create($attributes);

        $defaultPolicy = SlaPolicy::where('is_default', true)->first();
        if ($defaultPolicy) {
            $this->slaService->apply($ticket, $defaultPolicy);
        }

        return $ticket->fresh();
    }
}
