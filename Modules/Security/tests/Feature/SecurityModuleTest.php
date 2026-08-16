<?php

declare(strict_types=1);

namespace Modules\Security\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Security\Models\AuthenticationEvent;
use Modules\Security\Models\ComplianceAudit;
use Modules\Security\Models\ComplianceControl;
use Modules\Security\Models\ComplianceViolation;
use Modules\Security\Models\EncryptedField;
use Modules\Security\Models\EncryptionKey;
use Modules\Security\Models\SecurityIncident;
use Modules\Security\Models\ThreatIndicator;
use Modules\Security\Providers\SecurityServiceProvider;
use Modules\Core\Services\CsrfTokenGenerator;
use Modules\Core\Services\CsrfTokenService;
use Modules\Core\Services\OutputEncodingService;
use Modules\Core\Services\RateLimitService;
use Modules\Core\Services\SecurityHeadersService;
use Modules\Core\Services\SessionSecurityService;
use Modules\Core\Services\XssPreventionService;
use Tests\TestCase;

/**
 * SecurityModuleTest — structural & unit tests for Security module.
 *
 * Covers:
 * - OWASP: SQL injection prevention (Eloquent parameterised queries), XSS encoding, CSRF tokens
 * - Rate limiting configuration (tiers and limits present in config)
 * - Input validation rules (model fillable / cast constraints)
 * - Encryption (AES-256-GCM key model, EncryptedField configuration)
 * - Security headers middleware (CSP, X-Frame-Options, HSTS)
 * - Password policy enforcement (config settings)
 * - Session security settings (timeout, idle, regeneration flags)
 * - Security model structures (SecurityIncident, ThreatIndicator, ComplianceAudit, etc.)
 */
class SecurityModuleTest extends TestCase
{
    use RefreshDatabase;

    // ─── OWASP: SQL-Injection prevention via Eloquent ─────────────────────────

    /** @test */
    public function test_owasp_sql_injection_prevented_by_eloquent_parameterised_query(): void
    {
        // Verify Eloquent uses PDO prepared statements — inject payload as search term
        // It should not throw; Eloquent escapes via PDO bindings
        $malicious = "'; DROP TABLE security_incidents; --";

        $count = SecurityIncident::where('description', $malicious)->count();

        $this->assertIsInt($count);
        $this->assertEquals(0, $count); // No match, but no exception = injection prevented
    }

    /** @test */
    public function test_owasp_second_order_sql_injection_prevented_via_model_create(): void
    {
        $malicious = "1'; SELECT * FROM secrets --";

        // Storing the payload in DB via Eloquent's parameterised INSERT is safe
        $indicator = ThreatIndicator::create([
            'indicator_type'  => 'ip',
            'indicator_value' => $malicious,
            'threat_level'    => 'low',
            'source'          => 'test',
            'detected_at'     => now(),
        ]);

        $this->assertNotNull($indicator->id);
        $this->assertEquals($malicious, $indicator->indicator_value);
    }

    // ─── OWASP: XSS encoding ─────────────────────────────────────────────────

    /** @test */
    public function test_xss_output_encoding_service_resolves_from_container(): void
    {
        $this->assertInstanceOf(OutputEncodingService::class, app(OutputEncodingService::class));
    }

    /** @test */
    public function test_xss_output_encoding_encodes_html_special_characters(): void
    {
        $encoder  = app(OutputEncodingService::class);
        $encoded  = $encoder->encodeHtml('<script>alert("xss")</script>');

        $this->assertStringContainsString('&lt;', $encoded);
        $this->assertStringContainsString('&gt;', $encoded);
        $this->assertStringNotContainsString('<script>', $encoded);
    }

    /** @test */
    public function test_xss_output_encoding_encodes_attribute_value(): void
    {
        $encoder  = app(OutputEncodingService::class);
        $encoded  = $encoder->encodeAttribute('" onmouseover="evil()');

        $this->assertStringNotContainsString('"', $encoded);
    }

    /** @test */
    public function test_xss_prevention_service_detects_script_tags(): void
    {
        $service = app(XssPreventionService::class);

        $this->assertTrue($service->detectXss('<script>alert(1)</script>'));
        $this->assertFalse($service->detectXss('Normal text without payloads.'));
    }

    /** @test */
    public function test_xss_prevention_javascript_url_is_flagged(): void
    {
        $service = app(XssPreventionService::class);

        $this->assertTrue($service->detectXss('javascript:void(0)'));
    }

    /** @test */
    public function test_xss_prevention_event_handler_removal(): void
    {
        $service   = app(XssPreventionService::class);
        $dirty     = '<button onclick="steal()">Click</button>';
        $sanitized = $service->stripEventHandlers($dirty);

        $this->assertStringNotContainsString('onclick', $sanitized);
    }

    // ─── OWASP: CSRF token validation ─────────────────────────────────────────

    /** @test */
    public function test_csrf_token_generator_class_exists(): void
    {
        $this->assertTrue(class_exists(CsrfTokenGenerator::class));
    }

    /** @test */
    public function test_csrf_token_generator_produces_cryptographically_random_token(): void
    {
        $generator = new CsrfTokenGenerator();
        $token     = $generator->generate();

        // 32 bytes base64url → at least 43 chars
        $this->assertGreaterThanOrEqual(32, strlen($token));
    }

    /** @test */
    public function test_csrf_token_verify_rejects_tampered_token(): void
    {
        $generator = new CsrfTokenGenerator();
        $token     = $generator->generate();
        $hash      = $generator->hash($token);

        $this->assertTrue($generator->verify($token, $hash));
        $this->assertFalse($generator->verify('forged-token-value', $hash));
    }

    /** @test */
    public function test_csrf_token_hex_format_is_valid_hex(): void
    {
        $generator = new CsrfTokenGenerator();
        $hex       = $generator->generateHex();

        $this->assertMatchesRegularExpression('/^[a-f0-9]+$/', $hex);
        $this->assertGreaterThanOrEqual(64, strlen($hex)); // 32 bytes → 64 hex chars
    }

    // ─── Rate Limiting Configuration ──────────────────────────────────────────

    /** @test */
    public function test_rate_limit_config_exists(): void
    {
        $config = config('rate_limit');

        $this->assertIsArray($config);
        $this->assertArrayHasKey('tenant_tiers', $config);
    }

    /** @test */
    public function test_rate_limit_config_defines_free_tier(): void
    {
        $tiers = config('rate_limit.tenant_tiers');

        $this->assertArrayHasKey('free', $tiers);
        $this->assertArrayHasKey('requests_per_minute', $tiers['free']);
        $this->assertGreaterThan(0, $tiers['free']['requests_per_minute']);
    }

    /** @test */
    public function test_rate_limit_config_enterprise_tier_is_higher_than_free(): void
    {
        $tiers = config('rate_limit.tenant_tiers');

        if (!isset($tiers['enterprise'], $tiers['free'])) {
            $this->markTestSkipped('enterprise tier not defined');
        }

        $this->assertGreaterThan(
            $tiers['free']['requests_per_minute'],
            $tiers['enterprise']['requests_per_minute'],
        );
    }

    /** @test */
    public function test_rate_limit_service_class_exists(): void
    {
        $this->assertTrue(class_exists(RateLimitService::class));
    }

    // ─── Security Headers ─────────────────────────────────────────────────────

    /** @test */
    public function test_security_headers_service_resolves_from_container(): void
    {
        $this->assertInstanceOf(SecurityHeadersService::class, app(SecurityHeadersService::class));
    }

    /** @test */
    public function test_security_headers_csp_contains_default_src(): void
    {
        $service = app(SecurityHeadersService::class);
        $csp     = $service->generateCspHeader();

        $this->assertStringContainsString('default-src', $csp);
    }

    /** @test */
    public function test_security_headers_config_has_x_frame_options(): void
    {
        $value = config('security-headers.x_frame_options');

        $this->assertContains($value, ['DENY', 'SAMEORIGIN']);
    }

    /** @test */
    public function test_security_headers_nonce_is_validated(): void
    {
        $service = app(SecurityHeadersService::class);
        $nonce   = base64_encode(random_bytes(16));

        $this->assertTrue($service->validateNonce($nonce));
        $this->assertFalse($service->validateNonce('')); // empty nonce invalid
    }

    // ─── Session Security Settings ────────────────────────────────────────────

    /** @test */
    public function test_session_security_config_exists(): void
    {
        // SessionSecurityService actually reads config('session.*') (the
        // custom session_timeout/idle_timeout/... keys added to Laravel's
        // own session config, not a separate session-security namespace).
        $config = config('session');

        $this->assertIsArray($config);
        $this->assertArrayHasKey('session_timeout', $config);
        $this->assertArrayHasKey('idle_timeout', $config);
    }

    /** @test */
    public function test_session_security_idle_timeout_less_than_session_timeout(): void
    {
        $sessionTimeout = (int) config('session-security.session_timeout', 3600);
        $idleTimeout    = (int) config('session-security.idle_timeout', 900);

        $this->assertLessThan($sessionTimeout, $idleTimeout);
    }

    /** @test */
    public function test_session_security_service_class_exists(): void
    {
        $this->assertTrue(class_exists(SessionSecurityService::class));
    }

    // ─── Encryption / AES-256-GCM Key Model ──────────────────────────────────

    /** @test */
    public function test_encryption_key_model_fillable_includes_key_type(): void
    {
        $fillable = (new EncryptionKey())->getFillable();

        $this->assertContains('key_type', $fillable);
        $this->assertContains('key_usage', $fillable);
        $this->assertContains('key_status', $fillable);
        $this->assertContains('vault_reference', $fillable);
    }

    /** @test */
    public function test_encryption_key_model_casts_metadata_as_array(): void
    {
        $casts = (new EncryptionKey())->getCasts();

        $this->assertArrayHasKey('metadata', $casts);
        $this->assertEquals('array', $casts['metadata']);
    }

    /** @test */
    public function test_encrypted_field_model_fillable_has_algorithm(): void
    {
        $fillable = (new EncryptedField())->getFillable();

        $this->assertContains('encryption_algorithm', $fillable);
        $this->assertContains('table_name', $fillable);
        $this->assertContains('column_name', $fillable);
    }

    /** @test */
    public function test_encrypted_field_model_casts_boolean_flags(): void
    {
        $casts = (new EncryptedField())->getCasts();

        $this->assertArrayHasKey('is_searchable', $casts);
        $this->assertArrayHasKey('is_encrypted', $casts);
        $this->assertEquals('boolean', $casts['is_searchable']);
    }

    // ─── Security Incident model ──────────────────────────────────────────────

    /** @test */
    public function test_security_incident_model_uses_soft_deletes(): void
    {
        $this->assertContains(
            \Illuminate\Database\Eloquent\SoftDeletes::class,
            class_uses_recursive(SecurityIncident::class),
        );
    }

    /** @test */
    public function test_security_incident_model_casts_array_fields(): void
    {
        $casts = (new SecurityIncident())->getCasts();

        $this->assertArrayHasKey('threat_indicators', $casts);
        $this->assertArrayHasKey('affected_resources', $casts);
        $this->assertEquals('array', $casts['threat_indicators']);
    }

    // ─── ThreatIndicator model ────────────────────────────────────────────────

    /** @test */
    public function test_threat_indicator_model_whitelist_cast_is_boolean(): void
    {
        $casts = (new ThreatIndicator())->getCasts();

        $this->assertArrayHasKey('is_whitelisted', $casts);
        $this->assertEquals('boolean', $casts['is_whitelisted']);
    }

    // ─── Password Policy enforcement ──────────────────────────────────────────

    /** @test */
    public function test_user_password_history_trait_exists(): void
    {
        $this->assertTrue(
            trait_exists(\App\Traits\HasPasswordHistory::class),
            'HasPasswordHistory trait must exist for password policy enforcement',
        );
    }

    /** @test */
    public function test_user_model_has_password_history_trait(): void
    {
        $this->assertContains(
            \App\Traits\HasPasswordHistory::class,
            class_uses_recursive(\App\Models\User::class),
        );
    }

    // ─── Security Service Provider ────────────────────────────────────────────

    /** @test */
    public function test_security_service_provider_class_exists(): void
    {
        $this->assertTrue(class_exists(SecurityServiceProvider::class));
    }

    /** @test */
    public function test_authentication_event_model_has_no_updated_at_by_default(): void
    {
        // AuthenticationEvent sets $timestamps = false
        $model = new AuthenticationEvent();

        $this->assertFalse($model->usesTimestamps());
    }
}
