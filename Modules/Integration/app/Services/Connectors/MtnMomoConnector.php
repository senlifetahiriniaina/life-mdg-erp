<?php

declare(strict_types=1);

namespace Modules\Integration\Services\Connectors;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * MTN Mobile Money Connector
 *
 * Supported countries: GH, NG, CM, CI, UG, RW, ZM
 *
 * Uses the MTN MoMo Open API v1 (Collection + Disbursement products).
 * Reference: https://momodeveloper.mtn.com/
 */
class MtnMomoConnector
{
    private const BASE_URL_PROD    = 'https://proxy.momoapi.mtn.com';
    private const BASE_URL_SANDBOX = 'https://sandbox.momodeveloper.mtn.com';

    private string $subscriptionKey;
    private string $apiUser;
    private string $apiKey;
    private bool   $sandbox;
    private string $targetEnvironment;

    public function __construct(array $credentials, array $settings = [])
    {
        $this->subscriptionKey   = $credentials['subscription_key'] ?? '';
        $this->apiUser           = $credentials['api_user']         ?? '';
        $this->apiKey            = $credentials['api_key']          ?? '';
        $this->sandbox           = ($credentials['environment'] ?? 'sandbox') === 'sandbox';
        $this->targetEnvironment = $this->sandbox ? 'sandbox' : 'production';
    }

    // -------------------------------------------------------------------------
    // Collection (C2B)
    // -------------------------------------------------------------------------

    /**
     * Requests payment from a customer (C2B).
     * Customer receives a USSD or app prompt.
     *
     * @return array{reference_id: string, status: string}
     */
    public function requestPayment(string $phone, float $amount, string $currency, string $externalId): array
    {
        $referenceId = Str::uuid()->toString();
        $token       = $this->getAccessToken('collection');

        $response = Http::baseUrl($this->baseUrl())
            ->withHeaders([
                'Authorization'       => "Bearer {$token}",
                'X-Reference-Id'      => $referenceId,
                'X-Target-Environment' => $this->targetEnvironment,
                'Ocp-Apim-Subscription-Key' => $this->subscriptionKey,
                'Content-Type'        => 'application/json',
            ])
            ->post('/collection/v1_0/requesttopay', [
                'amount'     => (string) (int) round($amount),
                'currency'   => $currency,
                'externalId' => $externalId,
                'payer'      => [
                    'partyIdType' => 'MSISDN',
                    'partyId'     => $phone,
                ],
                'payerMessage' => 'Paiement WideHalo',
                'payeeNote'    => $externalId,
            ]);

        if (!$response->successful() && $response->status() !== 202) {
            Log::error('MtnMomo requestPayment failed', ['status' => $response->status(), 'body' => $response->body()]);
            throw new \RuntimeException('MTN MoMo payment request failed: ' . $response->body());
        }

        return [
            'reference_id' => $referenceId,
            'status'       => 'pending',
        ];
    }

    /**
     * Retrieves the status of a transaction.
     *
     * @return array{status: string, amount: float|null, currency: string|null, reason: string|null}
     */
    public function getTransactionStatus(string $referenceId): array
    {
        $token = $this->getAccessToken('collection');

        $response = Http::baseUrl($this->baseUrl())
            ->withHeaders([
                'Authorization'        => "Bearer {$token}",
                'X-Target-Environment' => $this->targetEnvironment,
                'Ocp-Apim-Subscription-Key' => $this->subscriptionKey,
            ])
            ->get("/collection/v1_0/requesttopay/{$referenceId}");

        if (!$response->successful()) {
            return ['status' => 'unknown', 'amount' => null, 'currency' => null, 'reason' => null];
        }

        $data   = $response->json();
        $status = match ($data['status'] ?? '') {
            'SUCCESSFUL' => 'completed',
            'PENDING'    => 'pending',
            'FAILED'     => 'failed',
            default      => 'unknown',
        };

        return [
            'status'   => $status,
            'amount'   => isset($data['amount']) ? (float) $data['amount'] : null,
            'currency' => $data['currency'] ?? null,
            'reason'   => $data['reason']   ?? null,
        ];
    }

    // -------------------------------------------------------------------------
    // Disbursement (B2C)
    // -------------------------------------------------------------------------

    /**
     * Transfers money to a phone number (B2C — pay supplier or employee).
     *
     * @return array{reference_id: string, status: string}
     */
    public function transfer(string $phone, float $amount, string $externalId): array
    {
        $referenceId = Str::uuid()->toString();
        $token       = $this->getAccessToken('disbursement');

        $response = Http::baseUrl($this->baseUrl())
            ->withHeaders([
                'Authorization'        => "Bearer {$token}",
                'X-Reference-Id'       => $referenceId,
                'X-Target-Environment' => $this->targetEnvironment,
                'Ocp-Apim-Subscription-Key' => $this->subscriptionKey,
                'Content-Type'         => 'application/json',
            ])
            ->post('/disbursement/v1_0/transfer', [
                'amount'     => (string) (int) round($amount),
                'currency'   => 'XOF',
                'externalId' => $externalId,
                'payee'      => [
                    'partyIdType' => 'MSISDN',
                    'partyId'     => $phone,
                ],
                'payerMessage' => 'Transfert WideHalo',
                'payeeNote'    => $externalId,
            ]);

        if (!$response->successful() && $response->status() !== 202) {
            throw new \RuntimeException('MTN MoMo transfer failed: ' . $response->body());
        }

        return [
            'reference_id' => $referenceId,
            'status'       => 'pending',
        ];
    }

    /**
     * Ping — checks the sandbox/production environment is reachable.
     */
    public function ping(): bool
    {
        try {
            $token    = $this->getAccessToken('collection');
            return !empty($token);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Countries where MTN MoMo is supported.
     *
     * @return string[]
     */
    public function getSupportedCountries(): array
    {
        return ['GH', 'NG', 'CM', 'CI', 'UG', 'RW', 'ZM'];
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function baseUrl(): string
    {
        return $this->sandbox ? self::BASE_URL_SANDBOX : self::BASE_URL_PROD;
    }

    /**
     * Fetches a bearer token from MTN's OAuth2 endpoint.
     *
     * @param  string $product  'collection' | 'disbursement'
     */
    private function getAccessToken(string $product): string
    {
        $response = Http::baseUrl($this->baseUrl())
            ->withBasicAuth($this->apiUser, $this->apiKey)
            ->withHeaders(['Ocp-Apim-Subscription-Key' => $this->subscriptionKey])
            ->post("/{$product}/token/");

        if (!$response->successful()) {
            throw new \RuntimeException("MTN MoMo token request failed for product [{$product}]");
        }

        return $response->json('access_token', '');
    }
}
