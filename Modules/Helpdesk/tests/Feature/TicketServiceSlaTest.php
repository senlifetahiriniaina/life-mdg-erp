<?php

declare(strict_types=1);

use Modules\Helpdesk\Models\SlaPolicy;
use Modules\Helpdesk\Services\TicketService;

/**
 * TicketService::createFromSource() (used by every module's
 * HelpdeskLinkable::raiseTicket()) looks up a default SlaPolicy and applies
 * it — but until SlaService::seedDefaultPolicies() existed, no policy was
 * ever marked is_default, so every cross-module ticket silently got no SLA.
 */
it('applies the seeded default SLA policy to a ticket created via createFromSource', function () {
    expect(SlaPolicy::count())->toBe(0);

    $service = app(TicketService::class);
    $ticket = $service->createFromSource(null, [
        'subject' => 'Facture erronée',
        'priority' => 'high',
    ]);

    // No default policy seeded yet — ticket creation must not fatal, it
    // just skips SLA assignment (pre-existing, correct behaviour).
    expect($ticket->sla_id)->toBeNull();

    app(\Modules\Helpdesk\Services\SlaService::class)->seedDefaultPolicies();

    $ticket2 = $service->createFromSource(null, [
        'subject' => 'Deuxième ticket',
        'priority' => 'medium',
    ]);

    $defaultPolicy = SlaPolicy::where('is_default', true)->first();

    expect($ticket2->sla_id)->toBe($defaultPolicy->id);
    expect($ticket2->sla_due_at)->not->toBeNull();
});
