<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Modules\Helpdesk\Models\Ticket;
use Modules\Helpdesk\Services\TicketService;

/**
 * Add to any Eloquent model (Accounting\Invoice, CRM\Contact,
 * Inventory\Product, ...) to let it raise a Helpdesk ticket about itself,
 * and to list the tickets already raised against it.
 *
 * Example:
 *   $invoice->raiseTicket(['subject' => 'Facture erronée', 'priority' => 'high']);
 *   $invoice->tickets; // tickets linked to this invoice
 */
trait HelpdeskLinkable
{
    public function tickets(): MorphMany
    {
        return $this->morphMany(Ticket::class, 'source');
    }

    /**
     * @param  array<string,mixed>  $data  subject, description, priority, type, team_id, reporter_id, customer_id
     */
    public function raiseTicket(array $data): Ticket
    {
        return app(TicketService::class)->createFromSource($this, $data);
    }
}
