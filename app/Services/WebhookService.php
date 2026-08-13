<?php
declare(strict_types=1);
namespace App\Services;

use App\Models\Webhook;
use App\Models\WebhookDelivery;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookService
{
    public function dispatch(string $event, array $payload): void
    {
        $webhooks = Webhook::where('is_active', true)
            ->whereJsonContains('events', $event)
            ->get();

        foreach ($webhooks as $webhook) {
            $this->deliver($webhook, $event, $payload);
        }
    }

    private function deliver(Webhook $webhook, string $event, array $payload): void
    {
        $body      = json_encode(['event' => $event, 'data' => $payload, 'timestamp' => now()->toIso8601String()]);
        $signature = 'sha256=' . hash_hmac('sha256', $body, $webhook->secret);

        try {
            $response = Http::timeout(10)
                ->withHeaders(['X-WideHalo-Signature' => $signature, 'Content-Type' => 'application/json'])
                ->send('POST', $webhook->url, ['body' => $body]);

            WebhookDelivery::create([
                'webhook_id'    => $webhook->id,
                'event'         => $event,
                'payload'       => $payload,
                'http_status'   => $response->status(),
                'response_body' => substr($response->body(), 0, 2000),
                'success'       => $response->successful(),
            ]);
        } catch (\Throwable $e) {
            Log::error("Webhook delivery failed for {$webhook->url}: " . $e->getMessage());
            WebhookDelivery::create([
                'webhook_id'    => $webhook->id,
                'event'         => $event,
                'payload'       => $payload,
                'http_status'   => null,
                'response_body' => $e->getMessage(),
                'success'       => false,
            ]);
        }
    }
}
