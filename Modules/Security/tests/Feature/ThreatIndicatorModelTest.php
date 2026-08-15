<?php

declare(strict_types=1);

namespace Modules\Security\Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Security\Models\ThreatIndicator;
use Tests\TestCase;

/**
 * ThreatIndicatorModelTest — unit-level tests for the ThreatIndicator model.
 *
 * Covers: creation, whitelist flag, expiry timestamp, threat levels,
 * indicator types, unique constraint, and query scopes.
 */
class ThreatIndicatorModelTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $company    = Company::factory()->create();
        $this->user = User::factory()->for($company)->create();
    }

    // ─── Basic creation ───────────────────────────────────────────────────────

    public function test_threat_indicator_can_be_created(): void
    {
        $indicator = ThreatIndicator::create([
            'indicator_type'  => 'ip_address',
            'indicator_value' => '192.0.2.1',
            'threat_level'    => 'high',
            'description'     => 'Known botnet C&C',
            'source'          => 'internal_detection',
            'is_whitelisted'  => false,
            'detected_at'     => now(),
        ]);

        $this->assertNotNull($indicator->id);
        $this->assertDatabaseHas('security_threat_indicators', [
            'indicator_value' => '192.0.2.1',
            'threat_level'    => 'high',
        ]);
    }

    // ─── Whitelist flag ───────────────────────────────────────────────────────

    public function test_is_whitelisted_cast_as_boolean(): void
    {
        $indicator = ThreatIndicator::create([
            'indicator_type'  => 'ip_address',
            'indicator_value' => '10.0.0.1',
            'threat_level'    => 'low',
            'description'     => 'Internal IP',
            'source'          => 'user_report',
            'is_whitelisted'  => true,
            'detected_at'     => now(),
        ]);

        $this->assertTrue($indicator->fresh()->is_whitelisted);
    }

    public function test_default_is_not_whitelisted(): void
    {
        $indicator = ThreatIndicator::create([
            'indicator_type'  => 'domain',
            'indicator_value' => 'evil-domain-test.example',
            'threat_level'    => 'medium',
            'description'     => 'Phishing domain',
            'source'          => 'threat_feed',
            'detected_at'     => now(),
        ]);

        $this->assertFalse($indicator->fresh()->is_whitelisted);
    }

    public function test_whitelisted_indicator_can_be_updated_to_false(): void
    {
        $indicator = ThreatIndicator::create([
            'indicator_type'  => 'ip_address',
            'indicator_value' => '172.16.0.50',
            'threat_level'    => 'low',
            'description'     => 'Previously whitelisted',
            'source'          => 'user_report',
            'is_whitelisted'  => true,
            'detected_at'     => now(),
        ]);

        $indicator->update(['is_whitelisted' => false]);

        $this->assertFalse($indicator->fresh()->is_whitelisted);
    }

    // ─── Expiry ───────────────────────────────────────────────────────────────

    public function test_expires_at_can_be_set(): void
    {
        $expiresAt = now()->addDays(30);

        $indicator = ThreatIndicator::create([
            'indicator_type'  => 'email',
            'indicator_value' => 'spammer@evil.example',
            'threat_level'    => 'medium',
            'description'     => 'Spam source',
            'source'          => 'threat_feed',
            'detected_at'     => now(),
            'expires_at'      => $expiresAt,
        ]);

        $this->assertNotNull($indicator->fresh()->expires_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $indicator->fresh()->expires_at);
    }

    public function test_expires_at_is_nullable(): void
    {
        $indicator = ThreatIndicator::create([
            'indicator_type'  => 'hash',
            'indicator_value' => 'abc123def456abc123def456abc123def456abc1',
            'threat_level'    => 'critical',
            'description'     => 'Malware hash',
            'source'          => 'threat_feed',
            'detected_at'     => now(),
        ]);

        $this->assertNull($indicator->fresh()->expires_at);
    }

    // ─── Threat levels ────────────────────────────────────────────────────────

    public function test_threat_level_low_stored_correctly(): void
    {
        $indicator = ThreatIndicator::create([
            'indicator_type'  => 'user_agent',
            'indicator_value' => 'SuspiciousBot/1.0',
            'threat_level'    => 'low',
            'description'     => 'Known scraper bot',
            'source'          => 'internal_detection',
            'detected_at'     => now(),
        ]);

        $this->assertEquals('low', $indicator->threat_level);
    }

    public function test_threat_level_critical_stored_correctly(): void
    {
        $indicator = ThreatIndicator::create([
            'indicator_type'  => 'ip_address',
            'indicator_value' => '203.0.113.1',
            'threat_level'    => 'critical',
            'description'     => 'Nation-state actor',
            'source'          => 'threat_feed',
            'detected_at'     => now(),
        ]);

        $this->assertEquals('critical', $indicator->threat_level);
    }

    // ─── Indicator types ──────────────────────────────────────────────────────

    public function test_domain_type_indicator_stored_correctly(): void
    {
        $indicator = ThreatIndicator::create([
            'indicator_type'  => 'domain',
            'indicator_value' => 'malware-c2.test.example',
            'threat_level'    => 'high',
            'description'     => 'C2 domain',
            'source'          => 'threat_feed',
            'detected_at'     => now(),
        ]);

        $this->assertEquals('domain', $indicator->indicator_type);
    }

    public function test_hash_type_indicator_stored_correctly(): void
    {
        $indicator = ThreatIndicator::create([
            'indicator_type'  => 'hash',
            'indicator_value' => 'deadbeefdeadbeefdeadbeefdeadbeefdeadbeef',
            'threat_level'    => 'high',
            'description'     => 'Malware hash',
            'source'          => 'threat_feed',
            'detected_at'     => now(),
        ]);

        $this->assertEquals('hash', $indicator->indicator_type);
    }

    // ─── Unique constraint ────────────────────────────────────────────────────

    public function test_duplicate_indicator_value_throws_exception(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        ThreatIndicator::create([
            'indicator_type'  => 'ip_address',
            'indicator_value' => '198.18.0.1',
            'threat_level'    => 'high',
            'description'     => 'First entry',
            'source'          => 'threat_feed',
            'detected_at'     => now(),
        ]);

        ThreatIndicator::create([
            'indicator_type'  => 'ip_address',
            'indicator_value' => '198.18.0.1', // duplicate
            'threat_level'    => 'medium',
            'description'     => 'Duplicate entry',
            'source'          => 'user_report',
            'detected_at'     => now(),
        ]);
    }

    // ─── Query filtering ──────────────────────────────────────────────────────

    public function test_filter_by_threat_level(): void
    {
        ThreatIndicator::create([
            'indicator_type' => 'ip_address', 'indicator_value' => '1.2.3.4',
            'threat_level' => 'high', 'description' => 'High', 'source' => 'threat_feed', 'detected_at' => now(),
        ]);
        ThreatIndicator::create([
            'indicator_type' => 'ip_address', 'indicator_value' => '1.2.3.5',
            'threat_level' => 'low', 'description' => 'Low', 'source' => 'threat_feed', 'detected_at' => now(),
        ]);

        $highThreats = ThreatIndicator::where('threat_level', 'high')->get();

        $this->assertEquals(1, $highThreats->count());
    }

    public function test_filter_non_whitelisted(): void
    {
        ThreatIndicator::create([
            'indicator_type' => 'ip_address', 'indicator_value' => '5.6.7.8',
            'threat_level' => 'medium', 'description' => 'Active', 'source' => 'threat_feed',
            'is_whitelisted' => false, 'detected_at' => now(),
        ]);
        ThreatIndicator::create([
            'indicator_type' => 'ip_address', 'indicator_value' => '5.6.7.9',
            'threat_level' => 'medium', 'description' => 'Whitelisted', 'source' => 'threat_feed',
            'is_whitelisted' => true, 'detected_at' => now(),
        ]);

        $active = ThreatIndicator::where('is_whitelisted', false)->get();

        $this->assertEquals(1, $active->count());
    }

    // ─── Source types ─────────────────────────────────────────────────────────

    public function test_user_report_source_stored(): void
    {
        $indicator = ThreatIndicator::create([
            'indicator_type'  => 'email',
            'indicator_value' => 'reported@spam.example',
            'threat_level'    => 'low',
            'description'     => 'User reported spam',
            'source'          => 'user_report',
            'detected_at'     => now(),
        ]);

        $this->assertEquals('user_report', $indicator->source);
    }

    // ─── Detected at cast ─────────────────────────────────────────────────────

    public function test_detected_at_cast_as_carbon(): void
    {
        $now = now();

        $indicator = ThreatIndicator::create([
            'indicator_type'  => 'ip_address',
            'indicator_value' => '198.51.100.99',
            'threat_level'    => 'medium',
            'description'     => 'Test',
            'source'          => 'internal_detection',
            'detected_at'     => $now,
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $indicator->fresh()->detected_at);
    }
}
