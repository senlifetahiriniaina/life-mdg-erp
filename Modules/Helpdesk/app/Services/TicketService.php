<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Services;

use Illuminate\Database\Eloquent\Model;
use Modules\Helpdesk\Models\Ticket;

/**
 * Central entry point for creating tickets from any other module in the
 * system (the `HelpdeskLinkable` trait and the generic
 * POST /api/v1/helpdesk/tickets endpoint both go through here), so every
 * caller gets the same ticket-number generation the dedicated
 * TicketController already applies, instead of each integration
 * re-implementing ticket creation (or writing raw SQL against the wrong
 * schema, as the previous Workflow action handler did). SLA assignment
 * happens uniformly for every creation path via Ticket::booted()'s
 * `created` hook, not here.
 */
class TicketService
{
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
            // Use the model's morph class (the short alias registered in
            // HelpdeskServiceProvider::registerTicketSourceMorphMap(), e.g.
            // 'employee', 'invoice') rather than the raw FQCN. Eloquent's
            // MorphMany::getMorphClass() resolves to the alias whenever the
            // source model's class is registered as a morph-map value, so
            // $model->tickets (the HelpdeskLinkable trait's own documented
            // contract) would otherwise query source_type='employee' against
            // rows written with source_type='Modules\HR\Models\Employee' —
            // never matching, so raiseTicket() succeeded but the reverse
            // ->tickets relation always came back empty.
            $attributes['source_type'] = $source->getMorphClass();
            $attributes['source_id'] = $source->getKey();
        }

        $ticket = Ticket::create($attributes);

        return $ticket->fresh();
    }
}
