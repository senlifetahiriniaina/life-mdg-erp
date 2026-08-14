<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\Core\Services\XssPreventionService;
use Modules\Security\Services\ThreatDetectionService;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mini application-layer WAF: blocks (or, in shadow mode, only logs)
 * requests from known-threat IPs (Modules\Security's ThreatIndicator) and
 * requests whose input matches an XSS pattern (Modules\Core's
 * XssPreventionService). Both services already existed but had zero
 * callers anywhere in the request lifecycle before this middleware.
 *
 * Defaults to shadow mode (config('security.request_inspection.shadow_mode'))
 * — logs matches to the 'security' channel without blocking, since
 * XssPreventionService's patterns can false-positive on legitimate
 * free-text fields and this is its first time running against real
 * traffic. Not a substitute for rate limiting (TenantRateLimitMiddleware /
 * throttleApi already cover request volume) — this is about content and IP
 * reputation.
 */
class RequestInspectionMiddleware
{
    public function __construct(
        private readonly XssPreventionService $xssPrevention,
        private readonly ThreatDetectionService $threatDetection,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->routeIsExcluded($request)) {
            return $next($request);
        }

        $shadowMode = (bool) config('security.request_inspection.shadow_mode', true);

        if ($ip = $request->ip()) {
            if ($this->isKnownThreatIp($ip)) {
                Log::channel('security')->warning('WAF: request from known threat IP', [
                    'ip' => $ip,
                    'path' => $request->path(),
                    'blocked' => ! $shadowMode,
                ]);

                if (! $shadowMode) {
                    abort(403, 'Forbidden');
                }
            }
        }

        if ($field = $this->firstXssField($request->all())) {
            Log::channel('security')->warning('WAF: XSS pattern detected in request input', [
                'field' => $field,
                'ip' => $request->ip(),
                'path' => $request->path(),
                'blocked' => ! $shadowMode,
            ]);

            if (! $shadowMode) {
                abort(422, 'Invalid input detected.');
            }
        }

        return $next($request);
    }

    private function isKnownThreatIp(string $ip): bool
    {
        $minutes = (int) config('security.request_inspection.ip_cache_minutes', 5);

        return Cache::remember(
            "waf:threat-ip:{$ip}",
            now()->addMinutes($minutes),
            fn () => $this->threatDetection->isKnownThreatIp($ip),
        );
    }

    /**
     * Recursively scans request input for XSS patterns, skipping excluded
     * fields. Returns the dotted key of the first match, or null.
     */
    private function firstXssField(array $input, string $prefix = ''): ?string
    {
        $excluded = config('security.request_inspection.excluded_fields', []);

        foreach ($input as $key => $value) {
            $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

            if (in_array($key, $excluded, true)) {
                continue;
            }

            if (is_array($value)) {
                if ($match = $this->firstXssField($value, $path)) {
                    return $match;
                }

                continue;
            }

            if (is_string($value) && $this->xssPrevention->detectXss($value)) {
                return $path;
            }
        }

        return null;
    }

    private function routeIsExcluded(Request $request): bool
    {
        $patterns = config('security.request_inspection.excluded_routes', []);

        foreach ($patterns as $pattern) {
            if ($request->routeIs($pattern) || $request->is(ltrim($pattern, '/'))) {
                return true;
            }
        }

        return false;
    }
}
