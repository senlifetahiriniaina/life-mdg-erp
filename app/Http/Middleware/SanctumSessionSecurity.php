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
 * Requires SessionSecurityService::createSession() to have been called at
 * token-issuance time (see AuthController::login()/register(),
 * TwoFactorController::verify()) — tokens issued before this was wired in, or
 * issued through a path that doesn't call createSession(), have no matching
 * SessionEnhanced record and will get a 419 on their first request here,
 * forcing a one-time re-login. This mirrors the existing
 * SessionSecurityMiddleware's own fail-closed behavior for stateful sessions.
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
