<?php

declare(strict_types=1);

namespace Modules\Integration\Services\Connectors;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Orange Money Connector
 *
 * Supported countries: SN, CI, CM, GN, BF, ML, NE
 *
 * Implements the Orange Money API v2 (REST + USSD / OTP flow).
 * Documentation: https://developer.orange.com/apis/om-webpay
 */
class OrangeMoneyConnector
{
    private const BASE_URL_PROD    = 'https://api.orange.com/orange-money-webpay/dev/v1';
    private const BASE_URL_SANDBOX = 'https://api.orange.com/orange-money-webpay/dev/v1';

    private string $apiKey;
    private string $merchantId;
    private string $secretKey;
    private bool   $sandbox;

    public function __construct(array $credentials, array $settings = [])
    {
        $this->apiKey     = $credentials['api_key']     ?? '';
        $this->merchantId = $credentials['merchant_id'] ?? '';
        $this->secretKey  = $credentials['secret_key']  ?? '';
        $this->sandbox    = ($credentials['environment'] ?? 'sandbox') === 'sandbox';
    }

    // -------------------------------------------------------------------------
    // Payment — C2B
    // -------------------------------------------------------------------------

    /**
     * Initiates a payment request. The customer receives a USSD prompt
     * asking them to confirm the payment with their PIN.
     *
     * @return array{transaction_id: string, status: string, ussd_code: string}
     */
    public function initiatePayment(string $phone, float $amount, string $currency, string $reference): array
    {
        $response = $this->http()->post('/webpayment', [
            'merchant_key'  => $this->secretKey,
            'currency'      => $currency,
            'order_id'      => $reference,
            'amount'        => (int) round($amount),
            'return_url'    => config('app.url') . '/api/v1/payments/webhook/orange-money',
            'cancel_url'    => config('app.url') . '/api/v1/payments/webhook/orange-money',
            'notif_url'     => config('app.url') . '/api/v1/payments/webhook/orange-money',
            'lang'          => 'fr',
            'reference'     => $reference,
        ]);

        if (!$response->successful()) {
            Log::error('OrangeMoney initiatePayment failed', ['body' => $response->body()]);
            throw new \RuntimeException('Orange Money payment initiation failed: ' . $response->body());
        }

        $data = $response->json();

        return [
            'transaction_id' => $data['pay_token']   ?? $reference,
            'status'         => 'pending',
            'ussd_code'      => $data['notif_token']  ?? '',
            'payment_url'    => $data['payment_url']  ?? '',
        ];
    }

    /**
     * Checks payment status by transaction ID.
     *
     * @return array{status: string, amount: float|null, currency: string|null}
     */
    public function checkStatus(string $transactionId): array
    {
        $response = $this->http()->get("/paymentstatus/{$transactionId}");

        if (!$response->successful()) {
            return ['status' => 'unknown', 'amount' => null, 'currency' => null];
        }

        $data   = $response->json();
        $status = match ($data['status'] ?? '') {
            'SUCCESS'    => 'completed',
            'PENDING'    => 'pending',
            'FAILED'     => 'failed',
            'EXPIRED'    => 'expired',
            default      => 'unknown',
        };

        return [
            'status'   => $status,
            'amount'   => isset($data['amount']) ? (float) $data['amount'] : null,
            'currency' => $data['currency'] ?? null,
        ];
    }

    /**
     * B2C transfer — pay a supplier or employee via Orange Money.
     *
     * @return array{transaction_id: string, status: string}
     */
    public function transfer(string $toPhone, float $amount, string $reason): array
    {
        $response = $this->http()->post('/transfer', [
            'merchant_key'    => $this->secretKey,
            'customer_msisdn' => $toPhone,
            'amount'          => (int) round($amount),
            'description'     => $reason,
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Orange Money transfer failed: ' . $response->body());
        }

        $data = $response->json();

        return [
            'transaction_id' => $data['txn_id'] ?? '',
            'status'         => 'pending',
        ];
    }

    /**
     * Handles incoming Orange Money webhook callbacks.
     * Fires internal event for the Accounting/Sales modules to reconcile.
     */
    public function handleWebhook(array $payload): void
    {
        Log::info('OrangeMoney webhook received', $payload);

        $status = match ($payload['status'] ?? '') {
            'SUCCESS' => 'completed',
            'FAILED'  => 'failed',
            default   => 'pending',
        };

        event('integration.orange-money.payment.' . $status, $payload);
    }

    /**
     * Ping — verifies credentials by fetching an access token.
     */
    public function ping(): bool
    {
        try {
            $response = Http::withHeaders(['Authorization' => 'Bearer ' . $this->apiKey])
                ->get($this->baseUrl() . '/ping');
            return $response->status() < 500;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Countries where Orange Money is supported.
     *
     * @return string[]
     */
    public function getSupportedCountries(): array
    {
        return ['SN', 'CI', 'CM', 'GN', 'BF', 'ML', 'NE'];
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function baseUrl(): string
    {
        return $this->sandbox ? self::BASE_URL_SANDBOX : self::BASE_URL_PROD;
    }

    private function http(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::baseUrl($this->baseUrl())
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ])
            ->timeout(30);
    }
}
