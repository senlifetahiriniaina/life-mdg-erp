<?php

declare(strict_types=1);

/*
 * These tests exercise App\Http\Middleware\SecurityHeaders -- the real, live,
 * globally-registered CSP/security-headers middleware (see bootstrap/app.php,
 * registered on both the `web` and `api` middleware groups).
 *
 * This file previously targeted Modules\Core\Services\SecurityHeadersService and
 * Modules\Core\Services\NonceManager, a duplicate config-driven CSP/nonce engine
 * that was never wired into any middleware, controller, or route -- grepping the
 * whole tree (app/, Modules/, bootstrap/, routes/) for real callers turned up
 * nothing but this test file, two other test files, and the config files that
 * fed it. Both classes have been deleted as dead code, along with config/csp.php
 * (which existed only to configure them, including a per-module CSP override for
 * an `Ecommerce` module that life-mdg-erp does not ship -- see CLAUDE.md's
 * 27-module scope table).
 *
 * Tests below assert the equivalent behaviour against the real middleware. A few
 * of the original tests had no real counterpart to rewrite against and were
 * dropped instead: the real middleware always enforces (no report-only mode),
 * applies one flat CSP to the whole app (no per-module policy), and generates a
 * single random_bytes(16) nonce per request with nothing stored/consumed/expired
 * server-side (no nonce store/validate/consume/expire lifecycle).
 */

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Security Headers', function () {
    describe('CSP Header Generation', function () {
        test('csp_header_is_present_in_non_api_responses', function () {
            $response = $this->get('/login');

            $response->assertStatus(200);
            $response->assertHeader('Content-Security-Policy');
        });

        test('csp_header_not_present_in_api_responses', function () {
            $response = $this->getJson('/api/health');

            $response->assertHeaderMissing('Content-Security-Policy');
        });

        test('csp_header_contains_nonce', function () {
            $response = $this->get('/login');

            $csp = $response->headers->get('Content-Security-Policy');

            expect($csp)->toContain('nonce-');
        });

        test('nonce_is_unique_per_request', function () {
            $response1 = $this->get('/login');
            $response2 = $this->get('/login');

            $nonce1 = extractNonce($response1->headers->get('Content-Security-Policy'));
            $nonce2 = extractNonce($response2->headers->get('Content-Security-Policy'));

            expect($nonce1)->not->toBeNull();
            expect($nonce2)->not->toBeNull();
            expect($nonce1)->not->toEqual($nonce2);
        });
    });

    describe('XSS Protection', function () {
        test('x_xss_protection_header_present', function () {
            $response = $this->get('/login');

            $response->assertHeader('X-XSS-Protection', '1; mode=block');
        });

        test('frame_options_prevent_clickjacking', function () {
            $response = $this->get('/login');

            $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        });

        test('content_type_options_prevent_mime_sniffing', function () {
            $response = $this->get('/login');

            $response->assertHeader('X-Content-Type-Options', 'nosniff');
        });
    });

    describe('HSTS Enforcement', function () {
        test('hsts_header_present_in_production', function () {
            $this->app['env'] = 'production';

            $response = $this->get('/login');

            $response->assertHeader('Strict-Transport-Security');
        });
    });

    describe('Nonce Management', function () {
        test('nonce_is_accessible_via_header', function () {
            $response = $this->get('/login');

            $nonce = $response->headers->get('X-CSP-Nonce');

            expect($nonce)->not->toBeNull();
            expect(strlen($nonce))->toBeGreaterThanOrEqual(8);
        });

        test('nonce_header_matches_nonce_embedded_in_csp', function () {
            // The real middleware generates a single nonce per request and reuses
            // it both in the X-CSP-Nonce response header and inside the CSP's
            // script-src directive -- there is no separate store/validate/consume
            // lifecycle (that concept only existed in the deleted, never-wired
            // NonceManager).
            $response = $this->get('/login');

            $nonce = $response->headers->get('X-CSP-Nonce');
            $csp = $response->headers->get('Content-Security-Policy');

            expect($csp)->toContain("nonce-{$nonce}");
        });

        test('nonce_is_valid_base64', function () {
            // App\Http\Middleware\SecurityHeaders generates the nonce via
            // base64_encode(random_bytes(16)), unpadded stripping is NOT applied.
            $response = $this->get('/login');

            $nonce = $response->headers->get('X-CSP-Nonce');

            expect((bool) preg_match('/^[a-zA-Z0-9+\/]+=*$/', $nonce))->toBeTrue();
        });
    });

    describe('Permission Policy', function () {
        test('permissions_policy_header_present', function () {
            $response = $this->get('/login');

            $response->assertHeader('Permissions-Policy');
        });

        test('permissions_policy_disables_sensitive_apis', function () {
            $response = $this->get('/login');

            $policy = $response->headers->get('Permissions-Policy');

            expect($policy)->toContain('camera=()');
            expect($policy)->toContain('microphone=()');
            expect($policy)->toContain('geolocation=()');
            expect($policy)->toContain('payment=()');
        });
    });

    describe('Referrer Policy', function () {
        test('referrer_policy_header_present', function () {
            $response = $this->get('/login');

            $response->assertHeader('Referrer-Policy');
        });

        test('referrer_policy_is_strict_origin', function () {
            $response = $this->get('/login');

            $policy = $response->headers->get('Referrer-Policy');

            expect($policy)->toEqual('strict-origin-when-cross-origin');
        });
    });

    describe('Cache Control', function () {
        test('api_responses_not_cacheable', function () {
            // Set by the separate, real App\Http\Middleware\CacheHeaders (also
            // globally registered) -- /api/health is on its user-specific
            // no-store allowlist.
            $response = $this->getJson('/api/health');

            $cacheControl = $response->headers->get('Cache-Control');

            expect($cacheControl)->toContain('no-cache');
            expect($cacheControl)->toContain('no-store');
        });

        test('static_assets_cached_long_term', function () {
            // Static assets should be cached with long TTL
            $response = $this->get('/css/app.css');

            // Status might be 404, but if it exists, check cache header
            if ($response->status() === 200) {
                $cacheControl = $response->headers->get('Cache-Control');

                expect($cacheControl)->toContain('public');
                expect($cacheControl)->toContain('max-age');
            }
        });
    });

    describe('Cross-Origin Policies', function () {
        test('cross_origin_opener_policy_header_present', function () {
            $response = $this->get('/login');

            $response->assertHeader('Cross-Origin-Opener-Policy');
        });

        test('cross_origin_resource_policy_header_present', function () {
            $response = $this->get('/login');

            $response->assertHeader('Cross-Origin-Resource-Policy');
        });
    });

    describe('Enforcing Mode Only', function () {
        test('csp_header_is_always_enforcing_never_report_only', function () {
            // The real middleware has no report-only mode (config('csp.report_only')
            // no longer exists -- it only ever fed the deleted, never-wired
            // SecurityHeadersService). It always sets the enforcing header.
            $response = $this->get('/login');

            $response->assertHeaderMissing('Content-Security-Policy-Report-Only');
            $response->assertHeader('Content-Security-Policy');
        });
    });
});

// Helper function to extract nonce from CSP header
function extractNonce(string $csp): ?string
{
    if (preg_match('/nonce-([a-zA-Z0-9+\/=]+)/', $csp, $matches)) {
        return $matches[1];
    }

    return null;
}
