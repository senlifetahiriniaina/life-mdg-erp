<?php

declare(strict_types=1);

use Modules\Logistics\Services\WebhookSecurityService;

describe('Webhook Security', function () {
    beforeEach(function () {
        $this->service = new WebhookSecurityService();
        $this->secret = 'webhook_secret_key_12345';
    });

    test('validate correct HMAC signature', function () {
        $payload = json_encode(['event' => 'shipment_updated']);
        $signature = $this->service->generateSignature($payload, $this->secret);

        $isValid = $this->service->validateSignature($payload, $signature, $this->secret);

        expect($isValid)->toBeTrue();
    });

    test('reject invalid HMAC signature', function () {
        $payload = json_encode(['event' => 'shipment_updated']);
        $invalidSignature = 'invalid_signature_xyz';

        $isValid = $this->service->validateSignature($payload, $invalidSignature, $this->secret);

        expect($isValid)->toBeFalse();
    });

    test('reject modified payload', function () {
        $payload = json_encode(['event' => 'shipment_updated']);
        $signature = $this->service->generateSignature($payload, $this->secret);

        $modifiedPayload = json_encode(['event' => 'shipment_deleted']);

        $isValid = $this->service->validateSignature($modifiedPayload, $signature, $this->secret);

        expect($isValid)->toBeFalse();
    });

    test('validate recent timestamp', function () {
        $timestamp = now()->toIso8601String();

        $isValid = $this->service->validateTimestamp($timestamp, 300);

        expect($isValid)->toBeTrue();
    });

    test('reject expired timestamp', function () {
        $timestamp = now()->subMinutes(10)->toIso8601String();

        $isValid = $this->service->validateTimestamp($timestamp, 300);

        expect($isValid)->toBeFalse();
    });

    test('accept timestamp at boundary', function () {
        $timestamp = now()->subSeconds(299)->toIso8601String();

        $isValid = $this->service->validateTimestamp($timestamp, 300);

        expect($isValid)->toBeTrue();
    });

    test('validate whitelisted IP', function () {
        $allowedIps = ['192.168.1.1', '10.0.0.1'];

        $isValid = $this->service->validateIp('192.168.1.1', $allowedIps);

        expect($isValid)->toBeTrue();
    });

    test('reject non-whitelisted IP', function () {
        $allowedIps = ['192.168.1.1', '10.0.0.1'];

        $isValid = $this->service->validateIp('203.0.113.42', $allowedIps);

        expect($isValid)->toBeFalse();
    });

    test('complete webhook validation success', function () {
        $payload = json_encode(['event' => 'shipment_picked_up']);
        $signature = $this->service->generateSignature($payload, $this->secret);
        $timestamp = $this->service->generateTimestamp();

        $headers = [
            'x-webhook-signature' => $signature,
            'x-webhook-timestamp' => $timestamp,
        ];

        $result = $this->service->validateWebhook(
            $headers,
            $payload,
            '192.168.1.1',
            $this->secret,
            300
        );

        expect($result['valid'])->toBeTrue();
        expect($result['errors'])->toBeEmpty();
    });

    test('complete webhook validation failure - missing signature', function () {
        $payload = json_encode(['event' => 'shipment_picked_up']);
        $timestamp = $this->service->generateTimestamp();

        $headers = [
            'x-webhook-timestamp' => $timestamp,
        ];

        $result = $this->service->validateWebhook(
            $headers,
            $payload,
            '192.168.1.1',
            $this->secret,
            300
        );

        expect($result['valid'])->toBeFalse();
        expect($result['errors'])->toContain('Missing webhook signature header');
    });

    test('complete webhook validation failure - invalid signature', function () {
        $payload = json_encode(['event' => 'shipment_picked_up']);
        $timestamp = $this->service->generateTimestamp();

        $headers = [
            'x-webhook-signature' => 'invalid_sig',
            'x-webhook-timestamp' => $timestamp,
        ];

        $result = $this->service->validateWebhook(
            $headers,
            $payload,
            '192.168.1.1',
            $this->secret,
            300
        );

        expect($result['valid'])->toBeFalse();
        expect($result['errors'])->toContain('Invalid webhook signature');
    });

    test('complete webhook validation failure - expired timestamp', function () {
        $payload = json_encode(['event' => 'shipment_picked_up']);
        $signature = $this->service->generateSignature($payload, $this->secret);
        $oldTimestamp = now()->subMinutes(10)->toIso8601String();

        $headers = [
            'x-webhook-signature' => $signature,
            'x-webhook-timestamp' => $oldTimestamp,
        ];

        $result = $this->service->validateWebhook(
            $headers,
            $payload,
            '192.168.1.1',
            $this->secret,
            300
        );

        expect($result['valid'])->toBeFalse();
        expect($result['errors'])->toContain('Webhook timestamp expired or invalid');
    });

    test('generate valid signature', function () {
        $payload = json_encode(['event' => 'test']);

        $signature = $this->service->generateSignature($payload, $this->secret);

        expect($signature)->not->toBeEmpty();
        expect(strlen($signature))->toBe(64); // SHA256 hex output
    });

    test('generate timestamp in ISO format', function () {
        $timestamp = $this->service->generateTimestamp();

        expect($timestamp)->toMatch('/^\d{4}-\d{2}-\d{2}T/');
    });
});
