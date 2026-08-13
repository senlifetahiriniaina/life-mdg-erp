<?php

declare(strict_types=1);

namespace Modules\Logistics\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;

/**
 * WebhookSecurityService handles webhook validation and security checks.
 */
class WebhookSecurityService
{
    /**
     * Allowed webhook sender IP addresses.
     */
    private const WHITELISTED_IPS = [
        // Add carrier IPs here
    ];

    /**
     * Validate webhook signature using HMAC-SHA256.
     */
    public function validateSignature(
        string $payload,
        string $signature,
        string $secret
    ): bool {
        $expectedSignature = hash_hmac('sha256', $payload, $secret, false);

        if (!hash_equals($expectedSignature, $signature)) {
            Log::warning('Invalid webhook signature', [
                'expected' => substr($expectedSignature, 0, 10),
                'received' => substr($signature, 0, 10),
            ]);
            return false;
        }

        return true;
    }

    /**
     * Validate webhook timestamp (prevent replay attacks).
     */
    public function validateTimestamp(string $timestamp, int $maxAgeSeconds = 300): bool
    {
        try {
            $webhookTime = strtotime($timestamp);
            $currentTime = time();
            $difference = abs($currentTime - $webhookTime);

            if ($difference > $maxAgeSeconds) {
                Log::warning('Webhook timestamp expired', [
                    'webhook_time' => $timestamp,
                    'age_seconds' => $difference,
                    'max_age_seconds' => $maxAgeSeconds,
                ]);
                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to validate webhook timestamp', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Validate webhook sender IP.
     */
    public function validateIp(string $senderIp, array $allowedIps = []): bool
    {
        // Use provided IPs or fallback to config
        $whitelist = !empty($allowedIps) ? $allowedIps : $this->getWhitelistedIps();

        if (empty($whitelist)) {
            // If no whitelist configured, allow (should configure in production)
            Log::info('No IP whitelist configured for webhooks');
            return true;
        }

        $allowed = in_array($senderIp, $whitelist, true);

        if (!$allowed) {
            Log::warning('Webhook from unauthorized IP', [
                'sender_ip' => $senderIp,
            ]);
        }

        return $allowed;
    }

    /**
     * Get whitelisted IPs from config.
     */
    private function getWhitelistedIps(): array
    {
        return config('logistics.webhook_ips', []);
    }

    /**
     * Validate complete webhook request.
     */
    public function validateWebhook(
        array $headers,
        string $payload,
        string $senderIp,
        string $secret,
        int $maxAgeSeconds = 300
    ): array {
        $errors = [];

        // Check signature
        $signature = $headers['x-webhook-signature'] ?? null;
        if (!$signature) {
            $errors[] = 'Missing webhook signature header';
        } elseif (!$this->validateSignature($payload, $signature, $secret)) {
            $errors[] = 'Invalid webhook signature';
        }

        // Check timestamp
        $timestamp = $headers['x-webhook-timestamp'] ?? null;
        if (!$timestamp) {
            $errors[] = 'Missing webhook timestamp header';
        } elseif (!$this->validateTimestamp($timestamp, $maxAgeSeconds)) {
            $errors[] = 'Webhook timestamp expired or invalid';
        }

        // Check IP
        if (!$this->validateIp($senderIp)) {
            $errors[] = 'Webhook sender IP not whitelisted';
        }

        if (!empty($errors)) {
            Log::warning('Webhook validation failed', [
                'errors' => $errors,
                'sender_ip' => $senderIp,
            ]);
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Log webhook validation failure.
     */
    public function logValidationFailure(
        string $reason,
        array $context = []
    ): void {
        Log::warning('Webhook validation failed: ' . $reason, $context);
    }

    /**
     * Generate webhook signature for outgoing requests.
     */
    public function generateSignature(string $payload, string $secret): string
    {
        return hash_hmac('sha256', $payload, $secret, false);
    }

    /**
     * Generate timestamp for outgoing requests.
     */
    public function generateTimestamp(): string
    {
        return now()->toIso8601String();
    }
}
