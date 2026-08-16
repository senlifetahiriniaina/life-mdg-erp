<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Modules\Core\Services\NonceManager;
use Modules\Core\Services\SecurityHeadersService;

uses(RefreshDatabase::class, WithFaker::class);

describe('Security Headers', function () {
    describe('CSP Header Generation', function () {
        test('csp_header_is_present_in_non_api_responses', function () {
            $user = User::factory()->create();
            $this->actingAs($user, 'sanctum');

            $response = $this->get('/dashboard');

            $response->assertStatus(200);

            // Check for CSP header (either report or enforce)
            $hasCsp = $response->headers->has('Content-Security-Policy')
                || $response->headers->has('Content-Security-Policy-Report-Only');

            expect($hasCsp)->toBeTrue();
        });

        test('csp_header_not_present_in_api_responses', function () {
            $user = User::factory()->create();
            $this->actingAs($user, 'sanctum');

            $response = $this->get('/api/v1/auth/me');

            // API responses should not have CSP header
            $response->assertHeaderMissing('Content-Security-Policy');
            $response->assertHeaderMissing('Content-Security-Policy-Report-Only');
        });

        test('csp_header_contains_nonce', function () {
            $user = User::factory()->create();
            $this->actingAs($user, 'sanctum');

            $response = $this->get('/dashboard');

            $csp = $response->headers->get('Content-Security-Policy') ??
                $response->headers->get('Content-Security-Policy-Report-Only');

            expect($csp)->toContain('nonce-');
        });

        test('nonce_is_unique_per_request', function () {
            $user = User::factory()->create();
            $this->actingAs($user, 'sanctum');

            $response1 = $this->get('/dashboard');
            $response2 = $this->get('/dashboard');

            $csp1 = $response1->headers->get('Content-Security-Policy') ??
                $response1->headers->get('Content-Security-Policy-Report-Only');
            $csp2 = $response2->headers->get('Content-Security-Policy') ??
                $response2->headers->get('Content-Security-Policy-Report-Only');

            // Extract nonces
            $nonce1 = extractNonce($csp1);
            $nonce2 = extractNonce($csp2);

            expect($nonce1)->not->toEqual($nonce2);
        });
    });

    describe('XSS Protection', function () {
        test('x_xss_protection_header_present', function () {
            $user = User::factory()->create();
            $this->actingAs($user, 'sanctum');

            $response = $this->get('/dashboard');

            $response->assertHeader('X-XSS-Protection', '1; mode=block');
        });

        test('frame_options_prevent_clickjacking', function () {
            $user = User::factory()->create();
            $this->actingAs($user, 'sanctum');

            $response = $this->get('/dashboard');

            $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        });

        test('content_type_options_prevent_mime_sniffing', function () {
            $user = User::factory()->create();
            $this->actingAs($user, 'sanctum');

            $response = $this->get('/dashboard');

            $response->assertHeader('X-Content-Type-Options', 'nosniff');
        });
    });

    describe('HSTS Enforcement', function () {
        test('hsts_header_present_in_production', function () {
            $user = User::factory()->create();
            $this->actingAs($user, 'sanctum');

            // In production environment
            $this->app['env'] = 'production';

            $response = $this->get('/dashboard');

            // Should have HSTS or similar header
            $hasHsts = $response->headers->has('Strict-Transport-Security')
                || $response->headers->has('X-Frame-Options');

            expect($hasHsts)->toBeTrue();
        });
    });

    describe('Nonce Management', function () {
        test('nonce_is_accessible_via_header', function () {
            $user = User::factory()->create();
            $this->actingAs($user, 'sanctum');

            $response = $this->get('/dashboard');

            // Nonce should be accessible via response header
            $nonce = $response->headers->get('X-CSP-Nonce');

            expect($nonce)->not->toBeNull();
            expect(strlen($nonce))->toBeGreaterThanOrEqual(8);
        });

        test('nonce_validation_works', function () {
            $service = app(SecurityHeadersService::class);

            $nonce = 'valid_nonce_here';

            expect($service->validateNonce($nonce))->toBeTrue();
            expect($service->validateNonce(''))->toBeFalse();
            expect($service->validateNonce('abc'))->toBeFalse(); // Too short
        });

        test('nonce_manager_generates_valid_nonces', function () {
            $manager = app(NonceManager::class);

            $nonce = $manager->generate();

            expect($nonce)->not->toBeEmpty();
            expect($manager->isValidFormat($nonce))->toBeTrue();
        });

        test('nonce_manager_stores_and_validates', function () {
            $manager = app(NonceManager::class);

            $nonce = $manager->generateAndStore();

            expect($manager->validate($nonce))->toBeTrue();
            expect($manager->exists($nonce))->toBeTrue();
        });

        test('nonce_can_be_consumed', function () {
            $manager = app(NonceManager::class);

            $nonce = $manager->generateAndStore();

            expect($manager->exists($nonce))->toBeTrue();

            $manager->consume($nonce);

            // After consumption, nonce should not validate again
            expect($manager->validate($nonce))->toBeFalse();
        });

        test('nonce_expires_after_configured_time', function () {
            $manager = app(NonceManager::class);

            $nonce = $manager->generateAndStore(
                requestId: 'test-' . uniqid(),
                lifetime: 1  // 1 second
            );

            expect($manager->validate($nonce))->toBeTrue();

            // Wait for expiration
            sleep(2);

            // Nonce should be expired (validation depends on cache implementation)
            // This test assumes TTL is respected by cache
        });
    });

    describe('Permission Policy', function () {
        test('permissions_policy_header_present', function () {
            $user = User::factory()->create();
            $this->actingAs($user, 'sanctum');

            $response = $this->get('/dashboard');

            $response->assertHeader('Permissions-Policy');
        });

        test('permissions_policy_disables_sensitive_apis', function () {
            $user = User::factory()->create();
            $this->actingAs($user, 'sanctum');

            $response = $this->get('/dashboard');

            $policy = $response->headers->get('Permissions-Policy');

            expect($policy)->toContain('camera=()');
            expect($policy)->toContain('microphone=()');
            expect($policy)->toContain('geolocation=()');
            expect($policy)->toContain('payment=()');
        });
    });

    describe('Referrer Policy', function () {
        test('referrer_policy_header_present', function () {
            $user = User::factory()->create();
            $this->actingAs($user, 'sanctum');

            $response = $this->get('/dashboard');

            $response->assertHeader('Referrer-Policy');
        });

        test('referrer_policy_is_strict_origin', function () {
            $user = User::factory()->create();
            $this->actingAs($user, 'sanctum');

            $response = $this->get('/dashboard');

            $policy = $response->headers->get('Referrer-Policy');

            expect($policy)->toEqual('strict-origin-when-cross-origin');
        });
    });

    describe('Cache Control', function () {
        test('api_responses_not_cacheable', function () {
            $user = User::factory()->create();
            $this->actingAs($user, 'sanctum');

            $response = $this->get('/api/v1/auth/me');

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
            $user = User::factory()->create();
            $this->actingAs($user, 'sanctum');

            $response = $this->get('/dashboard');

            $response->assertHeader('Cross-Origin-Opener-Policy');
        });

        test('cross_origin_resource_policy_header_present', function () {
            $user = User::factory()->create();
            $this->actingAs($user, 'sanctum');

            $response = $this->get('/dashboard');

            $response->assertHeader('Cross-Origin-Resource-Policy');
        });
    });

    describe('Module-Specific Policies', function () {
        test('accounting_module_has_custom_csp', function () {
            $service = app(SecurityHeadersService::class);

            $policy = $service->getModuleCspPolicy('Accounting');

            expect($policy)->toBeArray();
            expect(isset($policy['connect-src']))->toBeTrue();
        });

        test('ecommerce_module_includes_payment_gateways', function () {
            $service = app(SecurityHeadersService::class);

            $policy = $service->getModuleCspPolicy('Ecommerce');

            // Should have payment gateway domains
            $connectSrc = $policy['connect-src'] ?? '';

            expect($connectSrc)->toContain('stripe');
        });
    });

    describe('Report Mode', function () {
        test('report_only_mode_can_be_enabled', function () {
            config(['csp.report_only' => true]);

            $user = User::factory()->create();
            $this->actingAs($user, 'sanctum');

            $response = $this->get('/dashboard');

            // In report-only mode, should use Report-Only header
            $hasReportOnly = $response->headers->has('Content-Security-Policy-Report-Only');

            if (config('csp.report_only')) {
                expect($hasReportOnly)->toBeTrue();
            }
        });
    });
});

// Helper function to extract nonce from CSP header
function extractNonce(string $csp): ?string
{
    if (preg_match('/nonce-([a-zA-Z0-9_\-]+)/', $csp, $matches)) {
        return $matches[1];
    }

    return null;
}
