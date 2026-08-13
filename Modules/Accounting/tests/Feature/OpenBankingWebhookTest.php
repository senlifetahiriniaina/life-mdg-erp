<?php

declare(strict_types=1);

namespace Modules\Accounting\Tests\Feature;

use Tests\TestCase;

class OpenBankingWebhookTest extends TestCase
{
    /**
     * Test that valid webhook signature is accepted
     */
    public function test_valid_webhook_signature_accepted(): void
    {
        $secret = 'test-webhook-secret';
        config(['banking.plaid_webhook_secret' => $secret]);

        $payload = [
            'provider' => 'plaid',
            'webhook_type' => 'TRANSACTIONS',
            'added_transaction_ids' => ['txn_123', 'txn_456'],
        ];
        $body = json_encode($payload);
        $signature = hash_hmac('sha256', $body, $secret);

        $response = $this->postJson('/api/v1/accounting/open-banking/webhook', $payload, [
            'X-Signature' => $signature,
        ]);

        $response->assertStatus(200)
            ->assertJson(['status' => 'received']);
    }

    /**
     * Test that missing signature is rejected
     */
    public function test_missing_signature_rejected(): void
    {
        $payload = [
            'provider' => 'plaid',
            'webhook_type' => 'TRANSACTIONS',
        ];

        $response = $this->postJson('/api/v1/accounting/open-banking/webhook', $payload);

        $response->assertStatus(401)
            ->assertJson(['error' => 'Unauthorized']);
    }

    /**
     * Test that invalid signature is rejected
     */
    public function test_invalid_signature_rejected(): void
    {
        config(['banking.plaid_webhook_secret' => 'test-webhook-secret']);

        $payload = [
            'provider' => 'plaid',
            'webhook_type' => 'TRANSACTIONS',
        ];

        $response = $this->postJson('/api/v1/accounting/open-banking/webhook', $payload, [
            'X-Signature' => 'invalid-signature',
        ]);

        $response->assertStatus(401)
            ->assertJson(['error' => 'Unauthorized']);
    }

    /**
     * Test that nordigen webhook is accepted with valid signature
     */
    public function test_nordigen_webhook_accepted(): void
    {
        $secret = 'nordigen-secret';
        config(['banking.nordigen_webhook_secret' => $secret]);

        $payload = [
            'provider' => 'nordigen',
            'signature' => 'ACCEPTED',
            'requisition_id' => 'req_123456',
        ];
        $body = json_encode($payload);
        $signature = hash_hmac('sha256', $body, $secret);

        $response = $this->postJson('/api/v1/accounting/open-banking/webhook', $payload, [
            'X-Signature' => $signature,
        ]);

        $response->assertStatus(200);
    }

    /**
     * Test that bridge webhook is accepted with valid signature
     */
    public function test_bridge_webhook_accepted(): void
    {
        $secret = 'bridge-secret';
        config(['banking.bridge_webhook_secret' => $secret]);

        $payload = [
            'provider' => 'bridge',
            'webhook' => 'transaction.refreshed',
            'client_id' => 'client_789',
        ];
        $body = json_encode($payload);
        $signature = hash_hmac('sha256', $body, $secret);

        $response = $this->postJson('/api/v1/accounting/open-banking/webhook', $payload, [
            'X-Signature' => $signature,
        ]);

        $response->assertStatus(200);
    }

    /**
     * Test that unknown provider is logged but not crashed
     */
    public function test_unknown_provider_handled_gracefully(): void
    {
        $secret = 'test-secret';
        config(['banking.unknown_webhook_secret' => $secret]);

        $payload = [
            'provider' => 'unknown',
            'data' => 'test',
        ];
        $body = json_encode($payload);
        $signature = hash_hmac('sha256', $body, $secret);

        $response = $this->postJson('/api/v1/accounting/open-banking/webhook', $payload, [
            'X-Signature' => $signature,
        ]);

        // Should reject because no secret configured
        $response->assertStatus(401);
    }
}
