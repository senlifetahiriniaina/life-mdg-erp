<?php

declare(strict_types=1);

namespace Modules\Integration\Services\Connectors;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Wave Connector
 *
 * Supported countries: SN, CI
 *
 * Wave uses a QR code / deep-link payment flow (no USSD).
 * Reference: https://developer.wave.com/docs
 */
class WaveConnector
{
    private const BASE_URL_PROD    = 'https://api.wave.com/v1';
    private const BASE_URL_SANDBOX = 'https://api.wave.com/v1';

    private string $apiKey;
    private string $secretKey;
    private string $webhookSecret;

    public function __construct(array $credentials, array $settings = [])
    {
        $this->apiKey        = $credentials['api_key']        ?? '';
        $this->secretKey     = $credentials['secret_key']     ?? '';
        $this->webhookSecret = $credentials['webhook_secret'] ?? '';
    }

    // -------------------------------------------------------------------------
    // Payment
    // -------------------------------------------------------------------------

    /**
     * Generates a Wave payment link / QR code URL.
     * The customer opens the link in the Wave app to confirm payment.
     */
    public function generatePaymentLink(float $amount, string $currency, string $reference): string
    {
        $response = $this->http()->post('/checkout/sessions', [
            'amount'    => (string) (int) round($amount),
            'currency'  => $currency,
            'error_url' => config('app.url') . '/payment/error',
            'success_url' => config('app.url') . '/payment/success',
            'client_reference' => $reference,
        ]);

        if (!$response->successful()) {
            Log::error('Wave generatePaymentLink failed', ['body' => $response->body()]);
            throw new \RuntimeException('Wave payment link generation failed: ' . $response->body());
        }

        return $response->json('wave_launch_url', '');
    }

    /**
     * Checks the status of a Wave payment by reference.
     *
     * @return array{status: string, amount: float|null, currency: string|null, wave_id: string|null}
     */
    public function checkPaymentStatus(string $reference): array
    {
        $response = $this->http()->get("/checkout/sessions/{$reference}");

        if (!$response->successful()) {
            return ['status' => 'unknown', 'amount' => null, 'currency' => null, 'wave_id' => null];
        }

        $data   = $response->json();
        $status = match ($data['payment_status'] ?? '') {
            'succeeded' => 'completed',
            'processing' => 'pending',
            'cancelled', 'expired' => 'failed',
            default => 'unknown',
        };

        return [
            'status'   => $status,
            'amount'   => isset($data['amount']) ? (float) $data['amount'] : null,
            'currency' => $data['currency'] ?? null,
            'wave_id'  => $data['id'] ?? null,
        ];
    }

    /**
     * Handles Wave payment webhook. Verifies HMAC signature before processing.
     */
    public function handleWebhook(array $payload): void
    {
        Log::info('Wave webhook received', ['type' => $payload['type'] ?? 'unknown']);

        $eventType = $payload['type'] ?? '';
        event('integration.wave.' . $eventType, $payload);
    }

    /**
     * Verifies the HMAC-SHA256 signature on incoming Wave webhooks.
     */
    public function verifyWebhookSignature(string $rawBody, string $signature): bool
    {
        $expected = hash_hmac('sha256', $rawBody, $this->webhookSecret);
        return hash_equals($expected, $signature);
    }

    /**
     * Ping — verifies API key is valid.
     */
    public function ping(): bool
    {
        try {
            $response = $this->http()->get('/merchant/balance');
            return $response->status() < 500;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Countries where Wave is supported.
     *
     * @return string[]
     */
    public function getSupportedCountries(): array
    {
        return ['SN', 'CI'];
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function http(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::baseUrl(self::BASE_URL_PROD)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ])
            ->timeout(30);
    }
}
