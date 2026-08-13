<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Forces 2FA enrolment for roles where it is mandatory (admin / super-admin).
 *
 * - A mandatory-2FA user who has NOT enrolled is blocked from protected routes
 *   with 403 + setup_required, steering them to the enrolment endpoints.
 * - Users still holding a short-lived "2fa:challenge" token (mid login) are
 *   also blocked from anything except the challenge-completion endpoint.
 * - Everyone else passes through unchanged (non-admins are opt-in).
 */
class EnsureTwoFactorAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        // A challenge token may only be used to complete the 2FA challenge.
        $token = $user->currentAccessToken();
        if ($token !== null && method_exists($token, 'can') && $token->can('2fa:challenge')) {
            return response()->json([
                'message' => 'Two-factor challenge not completed.',
                'two_factor_required' => true,
            ], Response::HTTP_FORBIDDEN);
        }

        if ($user->requiresTwoFactor() && ! $user->hasTwoFactorEnabled()) {
            return response()->json([
                'message' => 'Two-factor authentication setup is required for your role.',
                'two_factor_setup_required' => true,
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
