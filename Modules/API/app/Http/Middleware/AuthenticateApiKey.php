<?php

declare(strict_types=1);

namespace Modules\API\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Modules\API\Models\ApiKey;
use Modules\API\Models\ApiRequest;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chantier 32.5 (14-layer audit of Modules\API) — layer 9 "fake/dead"
 * finding, activated rather than deleted: `Modules\API\Models\ApiKey` /
 * `ApiKeyController` are a real, working self-service credential-management
 * API ("API First" is a founding principle of this app) — but confirmed
 * empirically that ZERO code anywhere in the whole app ever validated an
 * incoming request against a real `api_keys` row. Every route in this app
 * authenticates via Sanctum session/token instead; a key minted through
 * `POST api/v1/api/keys` had no consumer of its own. As a direct
 * consequence, `Modules\API\Models\ApiRequest` (the per-key request log
 * `RequestLogController`/`ApiKeyController::logs()` already read from) had
 * zero writers anywhere either — every real tenant's "API request history"
 * screen has always been reading an unconditionally-empty table.
 *
 * This middleware is the missing producer for both: it authenticates a
 * request presenting a raw key via the `X-Api-Key` header against the real
 * `api_keys` table (prefix lookup + `Hash::check()` against the bcrypt
 * hash, `ApiKey::scopeActive()` for revoked/expired), enforces the key's
 * own `rate_limit` column (real, migrated, validated on creation — but,
 * like the auth pipeline itself, never read by anything until now) via
 * Laravel's `RateLimiter`, and logs a real `ApiRequest` row on the way out.
 *
 * Deliberately scoped to the one small, self-contained diagnostic route
 * this chantier wires it to (`GET api/v1/api/ping`) rather than retrofitted
 * across this app's real business endpoints — doing that for real would
 * mean a per-route product decision (which of ~30 modules' endpoints should
 * accept API-key auth instead of/alongside a Sanctum session?) and touching
 * every one of those modules' own route files, well outside a single
 * module's 14-layer audit. Documented as a real, larger follow-up rather
 * than silently expanded into here.
 */
class AuthenticateApiKey
{
    public function __construct(
        private readonly RateLimiter $limiter,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $rawKey = (string) $request->header('X-Api-Key', '');

        if ($rawKey === '') {
            abort(401, 'Missing API key. Send it via the X-Api-Key header.');
        }

        $key = $this->resolveKey($rawKey);

        if (! $key) {
            abort(401, 'Invalid, revoked, or expired API key.');
        }

        $limiterKey = "api-key:{$key->id}";
        $perHour = max(1, (int) $key->rate_limit);

        if ($this->limiter->tooManyAttempts($limiterKey, $perHour)) {
            abort(429, 'API key rate limit exceeded.');
        }

        $this->limiter->hit($limiterKey, 3600);

        // Best-effort — a stale/expired key must never block the request
        // it's meant to authenticate.
        try {
            $key->touchLastUsed();
        } catch (\Throwable) {
        }

        $request->attributes->set('api_key', $key);

        $start = microtime(true);
        $response = $next($request);
        $durationMs = (int) round((microtime(true) - $start) * 1000);

        $this->logRequest($request, $key, $response, $durationMs);

        return $response;
    }

    private function resolveKey(string $rawKey): ?ApiKey
    {
        $prefix = substr($rawKey, 0, 8);

        // key_prefix is indexed but not unique (only key_hash is) — narrow
        // by prefix first, then verify the real hash per candidate. In
        // practice this app only ever mints one key per prefix (40 random
        // chars after it), but the schema doesn't force that, so don't
        // assume it.
        return ApiKey::query()
            ->active()
            ->where('key_prefix', $prefix)
            ->get()
            ->first(fn (ApiKey $candidate) => Hash::check($rawKey, $candidate->key_hash));
    }

    private function logRequest(Request $request, ApiKey $key, Response $response, int $durationMs): void
    {
        // Logging a request must never break the response it's logging.
        try {
            ApiRequest::create([
                'tenant_id'   => $key->tenant_id,
                'api_key_id'  => $key->id,
                'user_id'     => $key->user_id,
                'method'      => $request->method(),
                'endpoint'    => $request->path(),
                'status_code' => $response->getStatusCode(),
                'duration_ms' => $durationMs,
                'ip_address'  => $request->ip(),
                'user_agent'  => (string) $request->userAgent(),
                'created_at'  => now(),
            ]);
        } catch (\Throwable) {
        }
    }
}
