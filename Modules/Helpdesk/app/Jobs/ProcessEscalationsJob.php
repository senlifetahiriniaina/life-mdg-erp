<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Helpdesk\Models\Ticket;
use Modules\Helpdesk\Services\EscalationService;

class ProcessEscalationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(EscalationService $escalationService): void
    {
        $openTickets = Ticket::whereNotIn('status', ['resolved', 'closed'])
            ->whereNull('deleted_at')
            ->get();

        $totalEscalations = 0;

        foreach ($openTickets as $ticket) {
            $events = $escalationService->checkAndEscalate($ticket);
            $totalEscalations += count($events);
        }

        Log::info("ProcessEscalationsJob: processed {$openTickets->count()} tickets, triggered {$totalEscalations} escalations.");
    }
}
