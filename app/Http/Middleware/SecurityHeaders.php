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
        $isApi = $request->is('api/*');

        // Generated before $next() so the Blade view (rendered inside $next()) can read
        // it via request()->attributes->get('csp_nonce') and tag its own inline scripts —
        // generating it after the view already rendered means the nonce never reaches the
        // HTML, so every inline <script> (Ziggy's @routes output included) is silently
        // blocked by the CSP header set below, even though the header itself is correct.
        if (! $isApi) {
            $nonce = base64_encode(random_bytes(16));
            $request->attributes->set('csp_nonce', $nonce);
        }

        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        $response->headers->set('Cross-Origin-Opener-Policy', config('security-headers.cross_origin_opener_policy', 'same-origin-allow-popups'));
        $response->headers->set('Cross-Origin-Resource-Policy', config('security-headers.cross_origin_resource_policy', 'same-origin'));

        if ($request->secure() || app()->isProduction()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        // CORS is handled by Laravel's native HandleCors middleware (config/cors.php),
        // which is origin-aware and safe with credentials — do not set CORS headers here.

        if (! $isApi) {
            $nonce = $request->attributes->get('csp_nonce');
            $response->headers->set('X-CSP-Nonce', $nonce);

            $directives = [
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
            ];

            // Only tell the browser to auto-upgrade http:// links to https:// when this
            // response was itself served over HTTPS (or in production, which is always
            // TLS-terminated) — unconditionally sending it breaks plain-HTTP local/E2E
            // environments: the browser silently rewrites the post-login redirect to
            // https://, which nothing is listening on, and the navigation just hangs.
            if ($request->secure() || app()->isProduction()) {
                $directives[] = 'upgrade-insecure-requests';
            }

            $response->headers->set('Content-Security-Policy', implode('; ', $directives));
        }

        return $response;
    }

    private function reverbWsOrigin(): string
    {
        // reverb.servers.reverb.host/port (REVERB_SERVER_HOST/PORT) is the server's
        // bind address (0.0.0.0 by default) — never a valid address for a browser to
        // connect to. The public-facing host/port the frontend actually connects to
        // (VITE_REVERB_HOST/PORT) comes from REVERB_HOST/PORT instead.
        $host = env('REVERB_HOST', 'localhost');
        $port = env('REVERB_PORT', 8080);

        return "ws://{$host}:{$port} wss://{$host}:{$port}";
    }
}
