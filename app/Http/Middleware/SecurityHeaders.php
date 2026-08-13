<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * HTTP Security Headers Middleware (OWASP Compliant)
 *
 * Applies defense-in-depth security headers on all responses:
 * - X-Content-Type-Options: Prevents MIME-sniffing attacks
 * - X-Frame-Options: Clickjacking protection (SAMEORIGIN)
 * - X-XSS-Protection: Legacy XSS filter
 * - Strict-Transport-Security: HTTPS enforcement (1 year, includeSubDomains)
 * - Permissions-Policy: Restricts camera, microphone, geolocation, payment APIs
 * - Content-Security-Policy: Inline scripts blocked except nonce, allows fonts/CDN
 *
 * CSP allows:
 * - API calls to Anthropic and OpenAI for AI features
 * - WebSocket connections (ws://, wss://) for Reverb real-time messaging
 * - External fonts from googleapis.com and gstatic.com
 * - Blob workers for offline processing
 *
 * Performance Impact: Negligible (~1-2ms header generation)
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $isApi = $request->is('api/*');

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

        if ($request->secure() || app()->isProduction()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        // CORS is handled by Laravel's native HandleCors middleware (config/cors.php),
        // which is origin-aware and safe with credentials — do not set CORS headers here.

        if (! $isApi) {
            $nonce = base64_encode(random_bytes(16));
            $request->attributes->set('csp_nonce', $nonce);

            $csp = implode('; ', [
                "default-src 'self'",
                "script-src 'self' 'nonce-{$nonce}' cdn.jsdelivr.net",
                "style-src 'self' 'unsafe-inline' fonts.googleapis.com cdn.jsdelivr.net",
                "font-src 'self' fonts.gstatic.com data:",
                "img-src 'self' data: blob: https:",
                "connect-src 'self' " . $this->reverbWsOrigin() . " https://api.anthropic.com https://api.openai.com",
                "worker-src 'self' blob:",
                "frame-src 'none'",
                "object-src 'none'",
                "base-uri 'self'",
                "form-action 'self'",
                "upgrade-insecure-requests",
            ]);

            $response->headers->set('Content-Security-Policy', $csp);
        }

        return $response;
    }

    private function reverbWsOrigin(): string
    {
        $host = config('reverb.servers.reverb.host', 'localhost');
        $port = config('reverb.servers.reverb.port', 8080);

        return "ws://{$host}:{$port} wss://{$host}:{$port}";
    }
}
