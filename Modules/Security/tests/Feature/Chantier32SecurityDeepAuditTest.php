<?php

declare(strict_types=1);

namespace Modules\Security\Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Security\Models\AuthenticationEvent;
use Modules\Security\Models\ComplianceAudit;
use Modules\Security\Models\ComplianceControl;
use Modules\Security\Models\ComplianceViolation;
use Modules\Security\Models\EncryptedField;
use Modules\Security\Models\EncryptionKey;
use Modules\Security\Models\SecurityIncident;
use Modules\Security\Models\ServiceIdentity;
use Modules\Security\Models\ThreatIndicator;
use Modules\Security\Models\TrustZone;
use Tests\TestCase;

/**
 * Chantier 32.3 — 14-layer deep audit of Modules\Security. Every finding
 * here was confirmed empirically (tinker / a real HTTP request against
 * realistic data) before being fixed, per the session's established
 * methodology (see CLAUDE.md "Méthodologie d'audit approfondi").
 */
class Chantier32SecurityDeepAuditTest extends TestCase
{
    use RefreshDatabase;

    private function seedGuard(): void
    {
        if (\Spatie\Permission\Models\Permission::count() === 0) {
            $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        }
    }

    private function securityAdmin(?Company $company = null, string $role = 'security-admin'): User
    {
        $this->seedGuard();
        $company ??= Company::factory()->create();
        $user = User::factory()->for($company)->create();
        $user->assignRole($role);

        return $user;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Layer 7 (RBAC) — headline finding: manager/employee (this app's
    // broadest, most commonly assigned roles) silently had full security.*
    // via the generic RolesAndPermissionsSeeder MODULES loop — including
    // security.encryption.rotate and security.identity.rotate (mint/rotate
    // service-to-service API credentials), despite the module's own routes
    // already scoping 2 of its 8 sub-resources to security-admin/admin/
    // super-admin only, confirming the intended audience. Both the seeder
    // grant and the route-level gate were fixed; both are locked in here.
    // ─────────────────────────────────────────────────────────────────────

    public function test_manager_and_employee_no_longer_hold_any_security_permission(): void
    {
        $this->seedGuard();

        foreach (['manager', 'employee'] as $roleName) {
            $role = \Spatie\Permission\Models\Role::where('name', $roleName)->firstOrFail();
            $count = $role->permissions()->where('name', 'like', 'security.%')->count();
            $this->assertSame(0, $count, "role [{$roleName}] should hold zero security.* permissions");
        }

        // admin/security-admin are unaffected.
        foreach (['admin', 'security-admin'] as $roleName) {
            $role = \Spatie\Permission\Models\Role::where('name', $roleName)->firstOrFail();
            $this->assertGreaterThan(0, $role->permissions()->where('name', 'like', 'security.%')->count());
        }
    }

    public function test_module_wide_route_gate_denies_a_plain_employee_over_http(): void
    {
        $company = Company::factory()->create();
        $employee = $this->securityAdmin($company, 'employee');

        $endpoints = [
            'incidents',
            'compliance/controls',
            'compliance/audits',
            'compliance/violations',
            'encryption/keys',
            'encryption/encrypted-fields',
            'threat-indicators',
            'trust-zones',
            'service-identities',
            'summary',
        ];

        foreach ($endpoints as $endpoint) {
            $this->actingAs($employee)
                ->getJson("/api/v1/security/{$endpoint}")
                ->assertForbidden();
        }
    }

    public function test_module_wide_route_gate_allows_security_admin_admin_and_super_admin(): void
    {
        foreach (['security-admin', 'admin', 'super-admin'] as $role) {
            $company = Company::factory()->create();
            $user = $this->securityAdmin($company, $role);

            $this->actingAs($user)
                ->getJson('/api/v1/security/incidents')
                ->assertOk();
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Layer 6 — re-confirmation of the already-fixed (Chantier 8.3cs)
    // string(36)-vs-int company_id comparison bug across all 8 Security
    // policies, over the real HTTP route rather than trusting the
    // changelog note. Every model below has a company_id, is directly
    // reachable via a show()-style endpoint, and is created either through
    // the real create endpoint (where one exists) or a factory (for the
    // models with no create producer — ComplianceAudit, ComplianceViolation,
    // EncryptedField).
    // ─────────────────────────────────────────────────────────────────────

    public function test_trust_zone_cross_tenant_isolation(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $userA = $this->securityAdmin($companyA);
        $userB = $this->securityAdmin($companyB);

        $zone = TrustZone::factory()->for($companyA)->create();

        $this->actingAs($userB)->getJson("/api/v1/security/trust-zones/{$zone->id}")->assertForbidden();
        $this->actingAs($userA)->getJson("/api/v1/security/trust-zones/{$zone->id}")->assertOk();
    }

    public function test_service_identity_cross_tenant_isolation(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $userA = $this->securityAdmin($companyA);
        $userB = $this->securityAdmin($companyB);

        $identity = ServiceIdentity::factory()->for($companyA)->create();

        $this->actingAs($userB)->getJson("/api/v1/security/service-identities/{$identity->id}")->assertForbidden();
        $this->actingAs($userA)->getJson("/api/v1/security/service-identities/{$identity->id}")->assertOk();
    }

    public function test_compliance_control_cross_tenant_isolation(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $userA = $this->securityAdmin($companyA);
        $userB = $this->securityAdmin($companyB);

        $control = ComplianceControl::factory()->for($companyA)->create();

        $this->actingAs($userB)->getJson("/api/v1/security/compliance/controls/{$control->id}")->assertForbidden();
        $this->actingAs($userA)->getJson("/api/v1/security/compliance/controls/{$control->id}")->assertOk();
    }

    public function test_compliance_audit_cross_tenant_isolation(): void
    {
        // Not covered by any prior chantier's test file — ComplianceAudit
        // has zero create endpoint (see the fake/dead findings below), so
        // built via factory, exactly like a real audit run would leave one.
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $userA = $this->securityAdmin($companyA);
        $userB = $this->securityAdmin($companyB);

        $audit = ComplianceAudit::factory()->for($companyA)->create();

        $this->actingAs($userB)->getJson("/api/v1/security/compliance/audits/{$audit->id}")->assertForbidden();
        $this->actingAs($userA)->getJson("/api/v1/security/compliance/audits/{$audit->id}")->assertOk();
    }

    public function test_compliance_violation_cross_tenant_isolation(): void
    {
        // Not covered by any prior chantier's test file.
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $userA = $this->securityAdmin($companyA);
        $userB = $this->securityAdmin($companyB);

        $control = ComplianceControl::factory()->for($companyA)->create();
        $violation = ComplianceViolation::factory()->for($companyA)->for($control, 'complianceControl')->create();

        $this->actingAs($userB)->getJson("/api/v1/security/compliance/violations/{$violation->id}")->assertForbidden();
        $this->actingAs($userA)->getJson("/api/v1/security/compliance/violations/{$violation->id}")->assertOk();
    }

    public function test_encryption_key_cross_tenant_isolation(): void
    {
        // Not covered by any prior chantier's test file.
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $userA = $this->securityAdmin($companyA);
        $userB = $this->securityAdmin($companyB);

        $key = EncryptionKey::factory()->for($companyA)->create();

        $this->actingAs($userB)->getJson("/api/v1/security/encryption/keys/{$key->id}")->assertForbidden();
        $this->actingAs($userA)->getJson("/api/v1/security/encryption/keys/{$key->id}")->assertOk();
    }

    public function test_security_incident_cross_tenant_isolation(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $userA = $this->securityAdmin($companyA);
        $userB = $this->securityAdmin($companyB);

        $incident = SecurityIncident::factory()->for($companyA)->create();

        $this->actingAs($userB)->getJson("/api/v1/security/incidents/{$incident->id}")->assertForbidden();
        $this->actingAs($userA)->getJson("/api/v1/security/incidents/{$incident->id}")->assertOk();
    }

    public function test_encrypted_field_cross_tenant_isolation(): void
    {
        // Not covered by any prior chantier's test file. EncryptedField has
        // no show() endpoint (only index/store) — cross-tenant read is
        // exercised via indexEncryptedFields()'s own company_id filter
        // instead of a show(), which is the real (only) way this data is
        // ever read back.
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $userA = $this->securityAdmin($companyA);
        $userB = $this->securityAdmin($companyB);

        EncryptedField::factory()->for($companyA)->create(['table_name' => 'crm_contacts', 'column_name' => 'ssn']);

        $listA = $this->actingAs($userA)->getJson('/api/v1/security/encryption/encrypted-fields');
        $listA->assertOk();
        $this->assertSame(1, $listA->json('total'));

        $listB = $this->actingAs($userB)->getJson('/api/v1/security/encryption/encrypted-fields');
        $listB->assertOk();
        $this->assertSame(0, $listB->json('total'));
    }

    // ─────────────────────────────────────────────────────────────────────
    // Layer 6/10 — new real bug found and fixed: EncryptionController::
    // storeEncryptedField()'s encryption_key_id validation only checked the
    // key existed anywhere, never that it belonged to the caller's own
    // company. Confirmed a company-A security-admin could name company B's
    // key id before this fix; now rejected with a 422.
    // ─────────────────────────────────────────────────────────────────────

    public function test_encrypted_field_cannot_reference_another_companys_encryption_key(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $userA = $this->securityAdmin($companyA);

        $keyB = EncryptionKey::factory()->for($companyB)->create();

        $response = $this->actingAs($userA)->postJson('/api/v1/security/encryption/encrypted-fields', [
            'table_name'            => 'crm_contacts',
            'column_name'           => 'national_id',
            'encryption_algorithm'  => 'AES-256-GCM',
            'encryption_key_id'     => $keyB->id,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('encryption_key_id');
        $this->assertDatabaseMissing('encrypted_fields', ['table_name' => 'crm_contacts', 'column_name' => 'national_id']);
    }

    public function test_encrypted_field_can_reference_own_companys_encryption_key(): void
    {
        $company = Company::factory()->create();
        $user = $this->securityAdmin($company);
        $key = EncryptionKey::factory()->for($company)->create();

        $response = $this->actingAs($user)->postJson('/api/v1/security/encryption/encrypted-fields', [
            'table_name'            => 'crm_contacts',
            'column_name'           => 'national_id',
            'encryption_algorithm'  => 'AES-256-GCM',
            'encryption_key_id'     => $key->id,
        ]);

        $response->assertCreated();
    }

    // ─────────────────────────────────────────────────────────────────────
    // Layer 9 (fake/dead) — legacy IncidentController::indexThreats/
    // storeThreat/whitelistThreat/unwhitelistThreat deleted (zero real
    // caller outside their own test file, confirmed via grep; a confirmed
    // duplicate of ThreatIndicatorController's own routes). whitelist/
    // unwhitelist ported onto ThreatIndicatorController instead of lost.
    // ─────────────────────────────────────────────────────────────────────

    public function test_legacy_threats_routes_no_longer_exist(): void
    {
        $user = $this->securityAdmin();

        $this->actingAs($user)->getJson('/api/v1/security/threats')->assertNotFound();
        $this->actingAs($user)->postJson('/api/v1/security/threats', [])->assertNotFound();
    }

    public function test_threat_indicator_whitelist_and_unwhitelist_work_on_the_consolidated_controller(): void
    {
        $user = $this->securityAdmin();
        $threat = ThreatIndicator::factory()->create(['is_whitelisted' => false]);

        $whitelist = $this->actingAs($user)->postJson("/api/v1/security/threat-indicators/{$threat->id}/whitelist");
        $whitelist->assertOk();
        $whitelist->assertJsonPath('data.is_whitelisted', true);

        $unwhitelist = $this->actingAs($user)->postJson("/api/v1/security/threat-indicators/{$threat->id}/unwhitelist");
        $unwhitelist->assertOk();
        $unwhitelist->assertJsonPath('data.is_whitelisted', false);
    }

    public function test_threat_indicator_duplicate_value_is_a_clean_422_not_a_500(): void
    {
        // Real bug found while consolidating the two threat-indicator create
        // paths: a real DB-level unique constraint on indicator_value
        // exists, but ThreatIndicatorController::store() never validated
        // it (unlike the now-deleted legacy route) — a duplicate insert
        // threw a raw QueryException before this fix.
        $user = $this->securityAdmin();
        ThreatIndicator::factory()->create(['indicator_value' => '203.0.113.9']);

        $response = $this->actingAs($user)->postJson('/api/v1/security/threat-indicators', [
            'indicator_type'  => 'ip',
            'indicator_value' => '203.0.113.9',
            'severity'        => 'high',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('indicator_value');
    }

    // ─────────────────────────────────────────────────────────────────────
    // Layer 9 (fake/dead) — GdprComplianceService: confirmed zero callers
    // anywhere in the app (not even its own tests), fake uniqid()-based
    // stub methods (processErasureRequest/exportUserData never persisted
    // anything real), fully superseded by Modules\Core's real GDPR
    // pipeline (Chantier 32.1). Deleted outright.
    // ─────────────────────────────────────────────────────────────────────

    public function test_gdpr_compliance_service_was_deleted_as_confirmed_dead_code(): void
    {
        $this->assertFalse(
            class_exists(\Modules\Security\Services\GdprComplianceService::class),
            'GdprComplianceService should have been deleted — confirmed zero real callers anywhere in the app.'
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // Layer 9 (sans producteur → activated) — AuthenticationEvent had a
    // real write method (SecurityAuditService::recordAuthEvent()) with zero
    // producer anywhere; security_authentication_events was never populated
    // by a real login/logout/failed-login. A new RecordAuthenticationEvent
    // listener (registered via a new Security EventServiceProvider) now
    // fires on Laravel's real Login/Logout/Failed auth events, verified here
    // over the real /login web route (Auth::attempt(), not the JWT API path
    // — app/Http/Controllers/Api/JwtAuthController never fires these events
    // at all, confirmed separately and out of this module's scope to fix).
    // ─────────────────────────────────────────────────────────────────────

    public function test_a_real_successful_login_now_writes_an_authentication_event(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correct-password')]);

        $this->assertSame(0, AuthenticationEvent::count());

        $response = $this->post('/login', [
            'email'    => $user->email,
            'password' => 'correct-password',
        ]);

        $response->assertRedirect();
        $this->assertAuthenticatedAs($user);

        $event = AuthenticationEvent::where('user_id', $user->id)->where('event_type', 'login')->first();
        $this->assertNotNull($event, 'a real successful login should have written a security_authentication_events row');
        $this->assertSame('success', $event->status);
        $this->assertSame($user->email, $event->user_email);
    }

    public function test_a_real_failed_login_now_writes_an_authentication_event(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correct-password')]);

        $response = $this->post('/login', [
            'email'    => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();

        $event = AuthenticationEvent::where('user_email', $user->email)->where('event_type', 'failed')->first();
        $this->assertNotNull($event, 'a real failed login should have written a security_authentication_events row');
        $this->assertSame('failure', $event->status);
        $this->assertSame('invalid_credentials', $event->failure_reason);
    }

    public function test_a_real_logout_now_writes_an_authentication_event(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->post('/logout');

        $response->assertRedirect();

        $event = AuthenticationEvent::where('user_id', $user->id)->where('event_type', 'logout')->first();
        $this->assertNotNull($event, 'a real logout should have written a security_authentication_events row');
        $this->assertSame('success', $event->status);
    }

    public function test_authentication_events_are_readable_by_security_admin_over_the_real_route(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correct-password')]);
        $this->post('/login', ['email' => $user->email, 'password' => 'correct-password']);

        $securityAdmin = $this->securityAdmin();
        $response = $this->actingAs($securityAdmin)->getJson('/api/v1/security/auth-events');

        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, $response->json('total'));
    }

    // ─────────────────────────────────────────────────────────────────────
    // Layer 4/9 — SecurityAuditService::getSecuritySummary() had zero real
    // callers and a hardcoded 'critical_threats' => 0 stub that was never a
    // real query. A new GET /security/summary endpoint gives it a real
    // caller (Index.vue), and critical_threats is now a real, scoped count.
    // ─────────────────────────────────────────────────────────────────────

    public function test_summary_endpoint_reports_a_real_critical_threats_count(): void
    {
        $user = $this->securityAdmin();
        ThreatIndicator::factory()->create(['threat_level' => 'critical', 'is_whitelisted' => false, 'expires_at' => null]);
        ThreatIndicator::factory()->create(['threat_level' => 'critical', 'is_whitelisted' => true, 'expires_at' => null]);
        ThreatIndicator::factory()->create(['threat_level' => 'low', 'is_whitelisted' => false, 'expires_at' => null]);
        ThreatIndicator::factory()->create([
            'threat_level' => 'critical', 'is_whitelisted' => false, 'expires_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/security/summary');

        $response->assertOk();
        // Exactly one of the four fixtures is critical + not whitelisted +
        // not expired — the whitelisted one, the low-severity one, and the
        // expired one must all be excluded.
        $this->assertSame(1, $response->json('data.critical_threats'));
    }

    public function test_summary_endpoint_reports_null_compliance_score_when_no_controls_exist(): void
    {
        $user = $this->securityAdmin();

        $response = $this->actingAs($user)->getJson('/api/v1/security/summary');

        $response->assertOk();
        $this->assertNull($response->json('data.compliance_score'));
    }

    public function test_summary_endpoint_reports_a_real_compliance_score(): void
    {
        $company = Company::factory()->create();
        $user = $this->securityAdmin($company);

        ComplianceControl::factory()->for($company)->create(['implementation_status' => 'implemented']);
        ComplianceControl::factory()->for($company)->create(['implementation_status' => 'implemented']);
        ComplianceControl::factory()->for($company)->create(['implementation_status' => 'planned']);

        $response = $this->actingAs($user)->getJson('/api/v1/security/summary');

        $response->assertOk();
        $this->assertEqualsWithDelta(66.7, $response->json('data.compliance_score'), 0.1);
    }

    public function test_summary_endpoint_scopes_open_incidents_and_auth_failures_by_the_callers_own_company(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $userA = $this->securityAdmin($companyA);

        SecurityIncident::factory()->for($companyA)->create(['incident_status' => 'open']);
        SecurityIncident::factory()->for($companyB)->create(['incident_status' => 'open']);

        $response = $this->actingAs($userA)->getJson('/api/v1/security/summary');

        $response->assertOk();
        $this->assertSame(1, $response->json('data.open_incidents'));
    }

    // ─────────────────────────────────────────────────────────────────────
    // Layer 6 — real bug found and fixed: RateLimitController::blockIp()
    // wrote a cache entry ("IP X has been blocked") that nothing anywhere
    // ever read — the block was never actually enforced. Now wired through
    // ThreatDetectionService::isKnownThreatIp(), and blockedIps() (which
    // used to unconditionally return []) now returns a real, TTL-aware list.
    // ─────────────────────────────────────────────────────────────────────

    public function test_blocking_an_ip_via_the_real_endpoint_is_actually_enforced(): void
    {
        $user = $this->securityAdmin();
        /** @var \Modules\Security\Services\ThreatDetectionService $threatDetection */
        $threatDetection = app(\Modules\Security\Services\ThreatDetectionService::class);

        $this->assertFalse($threatDetection->isKnownThreatIp('198.51.100.23'));

        $this->actingAs($user)->postJson('/api/v1/security/rate-limits/block-ip', [
            'ip' => '198.51.100.23',
        ])->assertOk();

        $this->assertTrue(
            $threatDetection->isKnownThreatIp('198.51.100.23'),
            'a manually-blocked IP should now be reported as a known threat by the same check the real WAF middleware uses'
        );

        $this->actingAs($user)->postJson('/api/v1/security/rate-limits/unblock-ip', [
            'ip' => '198.51.100.23',
        ])->assertOk();

        $this->assertFalse($threatDetection->isKnownThreatIp('198.51.100.23'));
    }

    public function test_blocked_ips_endpoint_returns_a_real_list_not_an_unconditional_empty_array(): void
    {
        $user = $this->securityAdmin();

        $this->actingAs($user)->postJson('/api/v1/security/rate-limits/block-ip', [
            'ip'     => '198.51.100.50',
            'reason' => 'Brute force attempt',
        ])->assertOk();

        $response = $this->actingAs($user)->getJson('/api/v1/security/rate-limits/blocked-ips');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertNotEmpty($data, 'blockedIps() used to unconditionally return an empty array regardless of what was blocked');
        $this->assertSame('198.51.100.50', $data[0]['ip']);
        $this->assertSame('Brute force attempt', $data[0]['reason']);

        $this->actingAs($user)->postJson('/api/v1/security/rate-limits/unblock-ip', [
            'ip' => '198.51.100.50',
        ])->assertOk();

        $afterUnblock = $this->actingAs($user)->getJson('/api/v1/security/rate-limits/blocked-ips');
        $this->assertEmpty($afterUnblock->json('data'));
    }

    // ─────────────────────────────────────────────────────────────────────
    // Layer 12 (API shape) — the JSON contract Index.vue actually consumes.
    // ─────────────────────────────────────────────────────────────────────

    public function test_summary_response_shape_matches_what_indexvue_actually_reads(): void
    {
        $user = $this->securityAdmin();

        $response = $this->actingAs($user)->getJson('/api/v1/security/summary');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => ['open_incidents', 'compliance_score', 'auth_failures_24h', 'critical_threats'],
        ]);
    }
}
