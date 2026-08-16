<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Services;

use Carbon\Carbon;
use Modules\Helpdesk\Models\SlaPolicy;
use Modules\Helpdesk\Models\Ticket;

class SlaService
{
    /**
     * `TicketService::createFromSource()` (used by every module's
     * `HelpdeskLinkable::raiseTicket()` and the generic ticket-creation
     * endpoint) looks up `SlaPolicy::where('is_default', true)->first()`
     * and silently skips SLA assignment when it finds none — which was
     * always, since nothing ever seeded a default policy. Idempotent
     * (firstOrCreate by name), same pattern as
     * ApprovalRoutingService::createDefaultWorkflows() /
     * InvoiceApprovalService::getOrCreateWorkflow().
     *
     * @return \Illuminate\Support\Collection<int, SlaPolicy>
     */
    public function seedDefaultPolicies(): \Illuminate\Support\Collection
    {
        $tiers = [
            ['name' => 'Faible priorité', 'priority' => 'low', 'response_time_minutes' => 480, 'resolution_time_minutes' => 4320],
            ['name' => 'Priorité moyenne', 'priority' => 'medium', 'response_time_minutes' => 240, 'resolution_time_minutes' => 1440, 'is_default' => true],
            ['name' => 'Priorité haute', 'priority' => 'high', 'response_time_minutes' => 60, 'resolution_time_minutes' => 480],
            ['name' => 'Urgent', 'priority' => 'urgent', 'response_time_minutes' => 15, 'resolution_time_minutes' => 240],
        ];

        return collect($tiers)->map(fn (array $tier) => SlaPolicy::firstOrCreate(
            ['name' => $tier['name']],
            $tier + ['escalation_enabled' => true, 'is_active' => true, 'is_default' => false]
        ));
    }

    /** Apply an SLA policy to a ticket, calculating the due datetime. */
    public function apply(Ticket $ticket, SlaPolicy $policy): void
    {
        $dueAt = $this->calculateDueAt($ticket->created_at ?? now(), (int) ceil($policy->resolution_time_minutes / 60), $policy->business_hours ?? null);

        $ticket->update([
            'sla_id' => $policy->id,
            'sla_due_at' => $dueAt,
            'sla_breached' => false,
        ]);
    }

    /** Check and mark any tickets that have breached their SLA. Returns count of newly breached tickets. */
    public function checkBreaches(): int
    {
        $breached = Ticket::query()
            ->whereNotNull('sla_due_at')
            ->where('sla_due_at', '<', now())
            ->where('sla_breached', false)
            ->whereNotIn('status', ['resolved', 'closed'])
            ->get();

        foreach ($breached as $ticket) {
            $ticket->update(['sla_breached' => true]);
        }

        return $breached->count();
    }

    /**
     * Calculate due datetime from a start time and resolution hours.
     * Respects business hours if provided: ['days' => [1..5], 'start' => '09:00', 'end' => '18:00'].
     *
     * @param  array<string,mixed>|null  $businessHours
     */
    private function calculateDueAt(Carbon $from, int $hours, ?array $businessHours): Carbon
    {
        if (! $businessHours || empty($businessHours['start'])) {
            return $from->copy()->addHours($hours);
        }

        $remaining = $hours;
        $current = $from->copy();
        $workDays = $businessHours['days'] ?? [1, 2, 3, 4, 5];
        $startHour = (int) explode(':', $businessHours['start'])[0];
        $endHour = (int) explode(':', $businessHours['end'] ?? '18:00')[0];
        $hoursPerDay = max(1, $endHour - $startHour);

        // Move to next business hour if currently outside
        if (! in_array($current->dayOfWeek, $workDays, true) || $current->hour >= $endHour) {
            do {
                $current->addDay()->setTime($startHour, 0, 0);
            } while (! in_array($current->dayOfWeek, $workDays, true));
        } elseif ($current->hour < $startHour) {
            $current->setTime($startHour, 0, 0);
        }

        while ($remaining > 0) {
            $availableToday = $endHour - $current->hour;

            if ($remaining <= $availableToday) {
                $current->addHours($remaining);
                $remaining = 0;
            } else {
                $remaining -= $availableToday;
                do {
                    $current->addDay()->setTime($startHour, 0, 0);
                } while (! in_array($current->dayOfWeek, $workDays, true));
            }
        }

        return $current;
    }
}
