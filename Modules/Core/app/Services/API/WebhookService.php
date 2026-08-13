<?php

declare(strict_types=1);

namespace Modules\Core\Services\API;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WebhookService
{
    private const MAX_RETRIES = 3;
    private const RETRY_DELAY = 5; // seconds

    /**
     * Register a webhook endpoint
     */
    public function registerWebhook(
        int $tenantId,
        string $url,
        array $events,
        ?string $secret = null,
        ?string $name = null
    ): array {
        $secret = $secret ?? Str::random(32);

        return [
            'id' => Str::uuid(),
            'tenant_id' => $tenantId,
            'url' => $url,
            'events' => $events,
            'secret' => $secret,
            'name' => $name,
            'active' => true,
            'created_at' => now()->toIso8601String(),
            'last_triggered_at' => null,
            'failures' => 0,
        ];
    }

    /**
     * Trigger a webhook event
     */
    public function trigger(string $event, array $data, int $tenantId): void
    {
        // In a real implementation, would query registered webhooks from database
        // For now, we'll demonstrate the pattern

        $webhooks = $this->getWebhooksForEvent($event, $tenantId);

        foreach ($webhooks as $webhook) {
            $this->sendWebhook($webhook, $event, $data);
        }
    }

    /**
     * Send webhook with retries
     */
    public function sendWebhook(array $webhook, string $event, array $data): void
    {
        $payload = $this->buildPayload($webhook, $event, $data);
        $signature = $this->generateSignature($payload, $webhook['secret']);

        $retries = 0;
        while ($retries < self::MAX_RETRIES) {
            try {
                $response = Http::withHeaders([
                    'X-Webhook-Signature' => $signature,
                    'X-Webhook-Event' => $event,
                    'X-Webhook-Delivery' => $this->generateDeliveryId(),
                    'Content-Type' => 'application/json',
                ])
                    ->timeout(30)
                    ->post($webhook['url'], $payload);

                if ($response->successful()) {
                    Log::info('Webhook delivered successfully', [
                        'webhook_id' => $webhook['id'],
                        'event' => $event,
                        'url' => $webhook['url'],
                    ]);

                    return;
                }

                // Retry on server error
                if ($response->serverError() && $retries < self::MAX_RETRIES - 1) {
                    $retries++;
                    sleep(self::RETRY_DELAY);
                    continue;
                }

                throw new \Exception("HTTP {$response->status()}: {$response->body()}");
            } catch (\Exception $e) {
                $retries++;

                if ($retries < self::MAX_RETRIES) {
                    sleep(self::RETRY_DELAY);
                } else {
                    Log::error('Webhook delivery failed after retries', [
                        'webhook_id' => $webhook['id'],
                        'event' => $event,
                        'url' => $webhook['url'],
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    /**
     * Verify webhook signature
     */
    public function verifySignature(string $payload, string $signature, string $secret): bool
    {
        $expectedSignature = $this->generateSignature($payload, $secret);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Build webhook payload
     */
    private function buildPayload(array $webhook, string $event, array $data): array
    {
        return [
            'event' => $event,
            'timestamp' => now()->toIso8601String(),
            'delivery_id' => $this->generateDeliveryId(),
            'webhook_id' => $webhook['id'],
            'data' => $data,
        ];
    }

    /**
     * Generate HMAC-SHA256 signature
     */
    private function generateSignature(array $payload, string $secret): string
    {
        $json = json_encode($payload);

        return 'sha256=' . hash_hmac('sha256', $json, $secret);
    }

    /**
     * Generate unique delivery ID
     */
    private function generateDeliveryId(): string
    {
        return Str::uuid();
    }

    /**
     * Get webhooks for specific event
     */
    private function getWebhooksForEvent(string $event, int $tenantId): array
    {
        // This would query from database in real implementation
        // For demonstration purposes, return empty array
        return [];
    }

    /**
     * Supported webhook events
     */
    public static function getSupportedEvents(): array
    {
        return [
            // CRM Events
            'crm.contact.created',
            'crm.contact.updated',
            'crm.contact.deleted',
            'crm.lead.created',
            'crm.lead.updated',
            'crm.lead.converted',
            'crm.opportunity.created',
            'crm.opportunity.updated',
            'crm.opportunity.closed',

            // Inventory Events
            'inventory.product.created',
            'inventory.product.updated',
            'inventory.movement.created',
            'inventory.stock.low',
            'inventory.stock.out',

            // Accounting Events
            'accounting.invoice.created',
            'accounting.invoice.paid',
            'accounting.invoice.overdue',
            'accounting.payment.recorded',

            // HR Events
            'hr.employee.hired',
            'hr.employee.departed',
            'hr.payroll.processed',
            'hr.review.completed',

            // Manufacturing Events
            'manufacturing.order.created',
            'manufacturing.order.completed',
            'manufacturing.qc.inspection',

            // System Events
            'system.sync_started',
            'system.sync_completed',
            'system.backup_completed',
        ];
    }
}
