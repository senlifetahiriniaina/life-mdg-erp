<?php

declare(strict_types=1);

namespace Modules\Workflow\Services\Actions;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * HttpActionHandler — Phase 39
 *
 * Handles outbound HTTP workflow actions: POST, GET, webhook dispatch.
 *
 * Security: validates URL against allowlist; blocks private/internal network IPs.
 * Template syntax: {{variable}} substitution from context.
 */
class HttpActionHandler
{
    /** @var array<string> Blocked private CIDR ranges (simplified pattern check) */
    private const BLOCKED_HOSTS = [
        '127.',
        '10.',
        '192.168.',
        'localhost',
        '0.0.0.0',
        '::1',
        'metadata.google.internal',
        '169.254.',
    ];

    /**
     * Dispatch an action by its dot-notation suffix.
     *
     * @param  array<string,mixed>  $params
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    public function dispatch(string $action, array $params, array $context): array
    {
        return match ($action) {
            'http.post'          => $this->post($params, $context),
            'http.get'           => $this->get($params, $context),
            'http.webhook_send'  => $this->webhookSend($params, $context),
            default => ['status' => 'skipped', 'reason' => "Unknown HTTP action: {$action}"],
        };
    }

    /**
     * action: http.post
     * Send a POST request to an external URL with optional {{variable}} body templating.
     *
     * @param  array<string,mixed>  $params   e.g. ['url' => '...', 'headers' => '{"X-API-Key":"..."}', 'body_template' => '...']
     * @param  array<string,mixed>  $context
     * @return array{response_status: int, response_body: string, status: string}
     */
    public function post(array $params, array $context): array
    {
        $url = $params['url'] ?? null;

        if (! $url) {
            return ['status' => 'error', 'reason' => 'Missing url parameter'];
        }

        $securityCheck = $this->validateUrl($url);
        if (! $securityCheck['allowed']) {
            return ['status' => 'error', 'reason' => $securityCheck['reason']];
        }

        $headers     = $this->parseHeaders($params['headers'] ?? '{}');
        $bodyTemplate = $params['body_template'] ?? json_encode($context);
        $body        = $this->interpolate($bodyTemplate, $context);

        try {
            $response = Http::timeout(10)
                ->withHeaders($headers)
                ->post($url, json_decode($body, true) ?? []);

            Log::info('WorkflowAction: http.post executed', [
                'url'    => $url,
                'status' => $response->status(),
            ]);

            return [
                'response_status' => $response->status(),
                'response_body'   => substr($response->body(), 0, 2048), // truncate for safety
                'status'          => $response->successful() ? 'success' : 'http_error',
            ];
        } catch (\Throwable $e) {
            Log::warning('WorkflowAction: http.post failed', ['url' => $url, 'error' => $e->getMessage()]);
            return ['response_status' => 0, 'response_body' => '', 'status' => 'exception', 'reason' => $e->getMessage()];
        }
    }

    /**
     * action: http.get
     * Send a GET request with optional query parameters.
     *
     * @param  array<string,mixed>  $params   e.g. ['url' => '...', 'query' => {'key': 'value'}]
     * @param  array<string,mixed>  $context
     * @return array{response_status: int, response_body: string, status: string}
     */
    public function get(array $params, array $context): array
    {
        $url = $params['url'] ?? null;

        if (! $url) {
            return ['status' => 'error', 'reason' => 'Missing url parameter'];
        }

        $securityCheck = $this->validateUrl($url);
        if (! $securityCheck['allowed']) {
            return ['status' => 'error', 'reason' => $securityCheck['reason']];
        }

        $headers = $this->parseHeaders($params['headers'] ?? '{}');
        $query   = is_array($params['query'] ?? null)
            ? $params['query']
            : (json_decode($params['query'] ?? '{}', true) ?? []);

        // Interpolate query values
        $query = array_map(fn ($v) => $this->interpolate((string) $v, $context), $query);

        try {
            $response = Http::timeout(10)
                ->withHeaders($headers)
                ->get($url, $query);

            return [
                'response_status' => $response->status(),
                'response_body'   => substr($response->body(), 0, 2048),
                'status'          => $response->successful() ? 'success' : 'http_error',
            ];
        } catch (\Throwable $e) {
            Log::warning('WorkflowAction: http.get failed', ['url' => $url, 'error' => $e->getMessage()]);
            return ['response_status' => 0, 'response_body' => '', 'status' => 'exception', 'reason' => $e->getMessage()];
        }
    }

    /**
     * action: http.webhook_send
     * Send context data to a configured outbound webhook URL.
     *
     * @param  array<string,mixed>  $params   e.g. ['webhook_url' => '...', 'event_type' => 'order.created']
     * @param  array<string,mixed>  $context
     * @return array{delivered: bool, response_status: int}
     */
    public function webhookSend(array $params, array $context): array
    {
        $webhookUrl = $params['webhook_url'] ?? null;
        $eventType  = $params['event_type'] ?? 'workflow.event';

        if (! $webhookUrl) {
            return ['status' => 'error', 'reason' => 'Missing webhook_url parameter'];
        }

        $securityCheck = $this->validateUrl($webhookUrl);
        if (! $securityCheck['allowed']) {
            return ['status' => 'error', 'reason' => $securityCheck['reason']];
        }

        $payload = [
            'event'      => $eventType,
            'timestamp'  => now()->toIso8601String(),
            'tenant_id'  => $context['tenant_id'] ?? null,
            'data'       => array_diff_key($context, array_flip(['tenant_id'])),
        ];

        // HMAC-SHA256 signature for webhook authenticity
        $secret    = $params['secret'] ?? config('workflow.webhook_secret', 'default_secret');
        $signature = hash_hmac('sha256', json_encode($payload), $secret);

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Content-Type'               => 'application/json',
                    'X-WideHalo-Signature'       => "sha256={$signature}",
                    'X-WideHalo-Event'           => $eventType,
                ])
                ->post($webhookUrl, $payload);

            return [
                'delivered'       => $response->successful(),
                'response_status' => $response->status(),
                'status'          => $response->successful() ? 'delivered' : 'failed',
            ];
        } catch (\Throwable $e) {
            Log::warning('WorkflowAction: webhook_send failed', ['url' => $webhookUrl, 'error' => $e->getMessage()]);
            return ['delivered' => false, 'response_status' => 0, 'status' => 'exception'];
        }
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Validate URL security: must be HTTPS and not point to internal network.
     *
     * @return array{allowed: bool, reason: string}
     */
    private function validateUrl(string $url): array
    {
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return ['allowed' => false, 'reason' => 'Invalid URL format'];
        }

        $parsed = parse_url($url);
        $host   = strtolower($parsed['host'] ?? '');

        foreach (self::BLOCKED_HOSTS as $blocked) {
            if (str_starts_with($host, $blocked) || $host === rtrim($blocked, '.')) {
                return ['allowed' => false, 'reason' => "URL points to blocked internal host: {$host}"];
            }
        }

        // Require HTTPS in production; allow HTTP in local/testing
        if (app()->environment('production') && ($parsed['scheme'] ?? '') !== 'https') {
            return ['allowed' => false, 'reason' => 'Only HTTPS URLs are allowed in production'];
        }

        return ['allowed' => true, 'reason' => ''];
    }

    /**
     * Parse JSON header string into array.
     *
     * @return array<string,string>
     */
    private function parseHeaders(string $headersJson): array
    {
        $headers = json_decode($headersJson, true);
        return is_array($headers) ? $headers : [];
    }

    /**
     * Replace {{variable}} placeholders in a template string with context values.
     */
    private function interpolate(string $template, array $context): string
    {
        foreach ($context as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $template = str_replace("{{" . $key . "}}", (string) $value, $template);
            }
        }
        return $template;
    }
}
