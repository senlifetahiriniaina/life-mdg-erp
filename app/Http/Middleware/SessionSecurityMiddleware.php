<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Core\Services\SessionSecurityService;
use Symfony\Component\HttpFoundation\Response;

/**
 * Session security hardening middleware.
 *
 * For authenticated requests it validates the session against the enhanced session
 * store (timeout, idle, user-mismatch/hijack) via SessionSecurityService and returns
 * 419 when the session is no longer valid. It also binds the session to the client's
 * user-agent fingerprint and surfaces a warning header during the idle grace period.
 *
 * IMPORTANT: only register this middleware on the stateful (web) stack once
 * SessionSecurityService::createSession() is wired into the login flow — otherwise
 * authenticated requests without an enhanced session record would be rejected (419).
 */
class SessionSecurityMiddleware
{
    public function __construct(private ?SessionSecurityService $sessionSecurity = null)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        // Pass-through when there is no session (e.g. stateless API/token requests).
        if (! $request->hasSession()) {
            return $next($request);
        }

        $session = $request->session();
        $validation = null;

        $user = $request->user();
        $userId = is_object($user) ? $user->getAuthIdentifier() : $user;

        if ($userId !== null && $this->sessionSecurity) {
            $validation = $this->sessionSecurity->validateSession($session->getId(), $userId, $request);

            if (! ($validation['valid'] ?? true)) {
                return response()->json([
                    'message' => $validation['reason'] ?? 'Session invalid or expired.',
                ], 419);
            }

            // Touch last-activity on the enhanced session record.
            if (! empty($validation['session'])) {
                $validation['session']->forceFill(['last_activity_at' => now()])->save();
            }
        }

        // Fingerprint binding to mitigate session fixation/hijacking.
        $fingerprint = hash('sha256', (string) $request->userAgent());
        if ($session->has('_secure_fingerprint')) {
            if ($session->get('_secure_fingerprint') !== $fingerprint) {
                $session->invalidate();
                $session->regenerateToken();
            }
        } else {
            $session->put('_secure_fingerprint', $fingerprint);
        }

        $response = $next($request);

        // Surface the idle grace-period warning to the client.
        if ($validation && ($validation['action'] ?? null) === 'warn') {
            $response->headers->set('X-Session-Warning', $validation['reason'] ?? 'Session idle');
        }

        return $response;
    }
}
