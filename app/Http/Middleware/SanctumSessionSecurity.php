<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Core\Services\SessionSecurityService;
use Symfony\Component\HttpFoundation\Response;

/**
 * Session-security hardening for Sanctum bearer-token API sessions.
 *
 * App\Http\Middleware\SessionSecurityMiddleware (device fingerprint/hijack
 * detection, idle timeout, concurrent-session limits) is built around PHP's
 * stateful $request->session() and never fires for stateless bearer-token
 * requests. This is the token-based equivalent: it keys
 * SessionSecurityService by the current Sanctum access token's ID instead of
 * a PHP session ID.
 *
 * A token that predates SessionSecurityService::createSession() being wired
 * into the login flow — or issued through a path that doesn't call it — has
 * no matching SessionEnhanced record. That used to be a hard 419, forcing a
 * re-login the moment this middleware got applied to any new route group:
 * every already-logged-in session, app-wide, would fail on its very next
 * request. Softened to auto-create the record on first sight instead (same
 * data createSession() would have written at login time, just a few
 * requests later) — this is what makes rolling session.security out beyond
 * HR safe. Every OTHER rejection reason (hijack/user-mismatch, fingerprint
 * mismatch, expiry, concurrent-session limit) still fails closed with a 419,
 * unchanged — "no record" is the only case where "we've never seen this
 * token" is expected/benign rather than suspicious.
 */
class SanctumSessionSecurity
{
    public function __construct(private ?SessionSecurityService $sessionSecurity = null)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        // Pass through unauthenticated requests and non-Sanctum guards untouched.
        if (! $user || ! $token || ! $this->sessionSecurity) {
            return $next($request);
        }

        $sessionId = (string) $token->id;
        $validation = $this->sessionSecurity->validateSession($sessionId, $user->getAuthIdentifier(), $request);

        if (! ($validation['valid'] ?? true) && ($validation['reason'] ?? null) === 'Session not found') {
            $validation = [
                'valid' => true,
                'session' => $this->sessionSecurity->createSession($sessionId, $user->getAuthIdentifier(), $request),
            ];
        }

        if (! ($validation['valid'] ?? true)) {
            return response()->json([
                'message' => $validation['reason'] ?? 'Session invalid or expired.',
            ], 419);
        }

        if (! empty($validation['session'])) {
            $validation['session']->forceFill(['last_activity_at' => now()])->save();
        }

        $response = $next($request);

        if (($validation['action'] ?? null) === 'warn') {
            $response->headers->set('X-Session-Warning', $validation['reason'] ?? 'Session idle');
        }

        return $response;
    }
}
