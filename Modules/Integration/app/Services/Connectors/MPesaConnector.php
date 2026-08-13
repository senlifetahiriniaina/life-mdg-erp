<?php

declare(strict_types=1);

namespace Modules\Integration\Services\Connectors;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * M-Pesa Connector (Safaricom)
 *
 * Supported countries: KE (Kenya), TZ (Tanzania)
 *
 * Uses the Daraja API v2 — STK Push (C2B) and B2C payment.
 * Reference: https://developer.safaricom.co.ke/APIs
 */
class MPesaConnector
{
    private const BASE_URL_PROD    = 'https://api.safaricom.co.ke';
    private const BASE_URL_SANDBOX = 'https://sandbox.safaricom.co.ke';

    private string $consumerKey;
    private string $consumerSecret;
    private string $shortcode;
    private string $passkey;
    private bool   $sandbox;

    public function __construct(array $credentials, array $settings = [])
    {
        $this->consumerKey    = $credentials['consumer_key']    ?? '';
        $this->consumerSecret = $credentials['consumer_secret'] ?? '';
        $this->shortcode      = $credentials['shortcode']       ?? '';
        $this->passkey        = $credentials['passkey']         ?? '';
        $this->sandbox        = ($credentials['environment'] ?? 'sandbox') === 'sandbox';
    }

    // -------------------------------------------------------------------------
    // C2B — STK Push (Lipa na M-Pesa Online)
    // -------------------------------------------------------------------------

    /**
     * Sends an STK Push request to the customer's phone.
     * Customer enters their M-Pesa PIN to confirm.
     *
     * @return array{checkout_request_id: string, merchant_request_id: string, status: string, customer_message: string}
     */
    public function stkPush(string $phone, float $amount, string $accountRef): array
    {
        $token     = $this->getAccessToken();
        $timestamp = now()->format('YmdHis');
        $password  = base64_encode($this->shortcode . $this->passkey . $timestamp);

        $response = $this->http($token)->post('/mpesa/stkpush/v1/processrequest', [
            'BusinessShortCode' => $this->shortcode,
            'Password'          => $password,
            'Timestamp'         => $timestamp,
            'TransactionType'   => 'CustomerPayBillOnline',
            'Amount'            => (int) round($amount),
            'PartyA'            => $this->normalizeMsisdn($phone),
            'PartyB'            => $this->shortcode,
            'PhoneNumber'       => $this->normalizeMsisdn($phone),
            'CallBackURL'       => config('app.url') . '/api/v1/payments/webhook/mpesa',
            'AccountReference'  => $accountRef,
            'TransactionDesc'   => 'Paiement WideHalo',
        ]);

        if (!$response->successful()) {
            Log::error('MPesa STK Push failed', ['body' => $response->body()]);
            throw new \RuntimeException('M-Pesa STK Push failed: ' . $response->body());
        }

        $data = $response->json();

        return [
            'checkout_request_id' => $data['CheckoutRequestID']  ?? '',
            'merchant_request_id' => $data['MerchantRequestID']  ?? '',
            'status'              => 'pending',
            'customer_message'    => $data['CustomerMessage']     ?? '',
        ];
    }

    /**
     * Queries the status of an STK Push transaction.
     *
     * @return array{status: string, result_code: int|null, result_desc: string|null}
     */
    public function checkStatus(string $checkoutRequestId): array
    {
        $token     = $this->getAccessToken();
        $timestamp = now()->format('YmdHis');
        $password  = base64_encode($this->shortcode . $this->passkey . $timestamp);

        $response = $this->http($token)->post('/mpesa/stkpushquery/v1/query', [
            'BusinessShortCode' => $this->shortcode,
            'Password'          => $password,
            'Timestamp'         => $timestamp,
            'CheckoutRequestID' => $checkoutRequestId,
        ]);

        if (!$response->successful()) {
            return ['status' => 'unknown', 'result_code' => null, 'result_desc' => null];
        }

        $data       = $response->json();
        $resultCode = (int) ($data['ResultCode'] ?? -1);
        $status     = $resultCode === 0 ? 'completed' : ($resultCode === 1032 ? 'cancelled' : 'failed');

        return [
            'status'      => $status,
            'result_code' => $resultCode,
            'result_desc' => $data['ResultDesc'] ?? null,
        ];
    }

    // -------------------------------------------------------------------------
    // B2C — Business to Customer
    // -------------------------------------------------------------------------

    /**
     * Sends money from the business shortcode to a customer phone.
     * Used for supplier payments, employee advances, refunds.
     *
     * @return array{conversation_id: string, originator_conversation_id: string, status: string}
     */
    public function b2cPayment(string $phone, float $amount, string $remarks): array
    {
        $token = $this->getAccessToken();

        $response = $this->http($token)->post('/mpesa/b2c/v1/paymentrequest', [
            'InitiatorName'      => 'WideHalo',
            'SecurityCredential' => $this->passkey,
            'CommandID'          => 'BusinessPayment',
            'Amount'             => (int) round($amount),
            'PartyA'             => $this->shortcode,
            'PartyB'             => $this->normalizeMsisdn($phone),
            'Remarks'            => $remarks,
            'QueueTimeOutURL'    => config('app.url') . '/api/v1/payments/webhook/mpesa',
            'ResultURL'          => config('app.url') . '/api/v1/payments/webhook/mpesa',
            'Occasion'           => '',
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('M-Pesa B2C payment failed: ' . $response->body());
        }

        $data = $response->json();

        return [
            'conversation_id'            => $data['ConversationID']           ?? '',
            'originator_conversation_id' => $data['OriginatorConversationID'] ?? '',
            'status'                     => 'pending',
        ];
    }

    /**
     * Ping — verifies credentials by fetching an access token.
     */
    public function ping(): bool
    {
        try {
            $token = $this->getAccessToken();
            return !empty($token);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Countries where M-Pesa is supported.
     *
     * @return string[]
     */
    public function getSupportedCountries(): array
    {
        return ['KE', 'TZ'];
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function getAccessToken(): string
    {
        $response = Http::baseUrl($this->baseUrl())
            ->withBasicAuth($this->consumerKey, $this->consumerSecret)
            ->get('/oauth/v1/generate', ['grant_type' => 'client_credentials']);

        if (!$response->successful()) {
            throw new \RuntimeException('M-Pesa OAuth token request failed');
        }

        return $response->json('access_token', '');
    }

    private function http(string $token): \Illuminate\Http\Client\PendingRequest
    {
        return Http::baseUrl($this->baseUrl())
            ->withHeaders([
                'Authorization' => "Bearer {$token}",
                'Content-Type'  => 'application/json',
            ])
            ->timeout(30);
    }

    private function baseUrl(): string
    {
        return $this->sandbox ? self::BASE_URL_SANDBOX : self::BASE_URL_PROD;
    }

    /** Normalise phone number to MSISDN format (254xxxxxxxxx for Kenya). */
    private function normalizeMsisdn(string $phone): string
    {
        $phone = preg_replace('/\D/', '', $phone);
        if (str_starts_with($phone, '0')) {
            $phone = '254' . substr($phone, 1);
        }
        return $phone;
    }
}
