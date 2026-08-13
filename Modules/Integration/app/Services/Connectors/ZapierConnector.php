<?php

declare(strict_types=1);

namespace Modules\Integration\Services\Connectors;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Zapier Connector — Outbound Webhook Hub
 *
 * Zapier integrates via "catch hook" triggers: WideHalo POSTs to a Zapier
 * webhook URL whenever a subscribed event fires in WideHalo.
 *
 * Subscription lifecycle:
 *   1. Zapier calls POST /api/v1/integrations/zapier/subscribe with hookUrl + event.
 *   2. WideHalo stores the subscription in `integration_zapier_subscriptions`.
 *   3. When the event fires in WideHalo, ZapierConnector::trigger() POSTs to all
 *      subscribed hook URLs for that event.
 *   4. Zapier calls DELETE /api/v1/integrations/zapier/unsubscribe/{id} on Zap pause/delete.
 *
 * No credentials required on construction — subscriptions are tenant-scoped.
 */
class ZapierConnector
{
    private const TABLE = 'integration_zapier_subscriptions';

    /** @var string[] All WideHalo events that can trigger Zapier Zaps */
    private const SUPPORTED_EVENTS = [
        // CRM
        'crm.contact.created',
        'crm.contact.updated',
        'crm.opportunity.won',
        'crm.opportunity.lost',

        // Sales
        'sales.order.created',
        'sales.order.confirmed',
        'sales.order.shipped',
        'sales.quotation.sent',

        // Accounting
        'accounting.invoice.posted',
        'accounting.invoice.paid',
        'accounting.expense.approved',

        // HR
        'hr.employee.created',
        'hr.leave.approved',
        'hr.payroll.processed',

        // Inventory
        'inventory.stock_below_reorder',
        'inventory.product.created',
        'inventory.stock.received',

        // Helpdesk
        'helpdesk.ticket.created',
        'helpdesk.ticket.resolved',
        'helpdesk.ticket.escalated',

        // Projects
        'projects.task.completed',
        'projects.project.created',

        // Manufacturing
        'manufacturing.order.created',
        'manufacturing.order.completed',

        // E-commerce
        'ecommerce.order.placed',
        'ecommerce.order.fulfilled',
    ];

    // ─── Subscriptions ────────────────────────────────────────────────────────

    /**
     * Register a Zapier hook URL for a given event.
     *
     * @param  string  $hookUrl   Zapier "catch hook" URL.
     * @param  string  $event     One of SUPPORTED_EVENTS.
     * @param  int     $tenantId  Tenant the subscription belongs to.
     * @return array{subscription_id: int, event: string, hook_url: string}
     */
    public function subscribe(string $hookUrl, string $event, int $tenantId): array
    {
        if (!in_array($event, self::SUPPORTED_EVENTS, true)) {
            throw new \InvalidArgumentException(
                "Unsupported Zapier event: {$event}. Supported events: " . implode(', ', self::SUPPORTED_EVENTS)
            );
        }

        if (!filter_var($hookUrl, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException("Invalid hook URL: {$hookUrl}");
        }

        // Prevent duplicate subscriptions for the same hook + event + tenant
        $existing = DB::table(self::TABLE)
            ->where('hook_url',  $hookUrl)
            ->where('event',     $event)
            ->where('tenant_id', $tenantId)
            ->value('id');

        if ($existing) {
            Log::info('Zapier subscription already exists', ['id' => $existing, 'event' => $event]);

            return [
                'subscription_id' => $existing,
                'event'           => $event,
                'hook_url'        => $hookUrl,
            ];
        }

        $id = DB::table(self::TABLE)->insertGetId([
            'hook_url'   => $hookUrl,
            'event'      => $event,
            'tenant_id'  => $tenantId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Log::info('Zapier subscription created', ['id' => $id, 'event' => $event, 'tenant_id' => $tenantId]);

        return [
            'subscription_id' => $id,
            'event'           => $event,
            'hook_url'        => $hookUrl,
        ];
    }

    /**
     * Remove a Zapier subscription (called when user pauses or deletes a Zap).
     *
     * @param  int  $subscriptionId  ID returned by subscribe().
     */
    public function unsubscribe(int $subscriptionId): void
    {
        $deleted = DB::table(self::TABLE)->where('id', $subscriptionId)->delete();

        if (!$deleted) {
            Log::warning('Zapier unsubscribe: subscription not found', ['id' => $subscriptionId]);

            return;
        }

        Log::info('Zapier subscription deleted', ['id' => $subscriptionId]);
    }

    // ─── Triggering ───────────────────────────────────────────────────────────

    /**
     * Fire an event to all Zapier hook URLs subscribed to it.
     *
     * POSTs a JSON payload to each registered hook URL. Failed requests are
     * logged but do not throw — Zapier handles retries on its side.
     *
     * @param  string  $event     WideHalo event key (e.g. 'crm.contact.created').
     * @param  array<string, mixed>  $data   Payload to send (no PII without consent).
     */
    public function trigger(string $event, array $data): void
    {
        $subscriptions = DB::table(self::TABLE)
            ->where('event', $event)
            ->get(['id', 'hook_url', 'tenant_id']);

        if ($subscriptions->isEmpty()) {
            return;
        }

        $payload = [
            'event'      => $event,
            'triggered_at' => now()->toIso8601String(),
            'data'       => $data,
        ];

        foreach ($subscriptions as $subscription) {
            try {
                $response = Http::timeout(10)
                    ->withHeaders(['Content-Type' => 'application/json'])
                    ->post($subscription->hook_url, $payload);

                if ($response->failed()) {
                    Log::warning('Zapier trigger HTTP error', [
                        'subscription_id' => $subscription->id,
                        'event'           => $event,
                        'status'          => $response->status(),
                        'hook_url'        => $subscription->hook_url,
                    ]);
                } else {
                    Log::debug('Zapier trigger sent', [
                        'subscription_id' => $subscription->id,
                        'event'           => $event,
                        'status'          => $response->status(),
                    ]);
                }

                // Update last_triggered_at
                DB::table(self::TABLE)
                    ->where('id', $subscription->id)
                    ->update(['last_triggered_at' => now(), 'updated_at' => now()]);

            } catch (\Throwable $e) {
                Log::error('Zapier trigger exception', [
                    'subscription_id' => $subscription->id,
                    'event'           => $event,
                    'error'           => $e->getMessage(),
                ]);
            }
        }
    }

    // ─── Metadata ─────────────────────────────────────────────────────────────

    /**
     * Returns all WideHalo events that can trigger Zapier Zaps.
     *
     * @return string[]
     */
    public function getSupportedEvents(): array
    {
        return self::SUPPORTED_EVENTS;
    }

    /**
     * List all active subscriptions for a tenant, optionally filtered by event.
     *
     * @param  int          $tenantId
     * @param  string|null  $event
     * @return array<int, array{id: int, event: string, hook_url: string, created_at: string}>
     */
    public function listSubscriptions(int $tenantId, ?string $event = null): array
    {
        $query = DB::table(self::TABLE)->where('tenant_id', $tenantId);

        if ($event !== null) {
            $query->where('event', $event);
        }

        return $query
            ->orderBy('event')
            ->get(['id', 'event', 'hook_url', 'created_at', 'last_triggered_at'])
            ->map(fn ($row) => (array) $row)
            ->all();
    }
}
