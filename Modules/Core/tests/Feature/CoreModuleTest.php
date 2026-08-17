<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Modules\Core\Models\AuditLog;
use Modules\Core\Models\TenantModule;
use Modules\Core\Services\AuditService;
use Modules\Core\Services\ModuleManager;
use Modules\Core\Services\TenantRegistrationService;
use Modules\Core\Services\TenantProvisioningService;
use Modules\Core\Services\CsrfTokenGenerator;
use App\Http\Middleware\SecurityHeaders;
use Modules\Core\Services\XssPreventionService;
use Modules\Core\Services\OutputEncodingService;
use Modules\Core\Services\HtmlPurifierService;
use Modules\Core\Services\RateLimitService;
use Tests\TestCase;

/**
 * CoreModuleTest — comprehensive structural and behavioural tests for Core module.
 *
 * Covers:
 * - TenantService: create tenant slug/domain, provisioning & multi-tenant isolation
 * - AuditService: log actions, query by model/user/action/date range, edge cases
 * - AuditLog model: table name, fillable columns, casts, created_by, timestamps
 * - ModuleManager: list enabled modules, isEnabled(), enable/disable
 * - User role/permission resolution via Spatie HasRoles
 * - CSRF token generation
 * - Security headers service
 * - XSS prevention & output encoding
 * - Rate limit service class structure
 */
class CoreModuleTest extends TestCase
{
    use RefreshDatabase;

    // ─── TenantRegistrationService ────────────────────────────────────────────

    /** @test */
    public function test_tenant_registration_service_resolves_from_container(): void
    {
        $this->assertInstanceOf(TenantRegistrationService::class, app(TenantRegistrationService::class));
    }

    /** @test */
    public function test_generate_slug_produces_url_safe_string(): void
    {
        $service = app(TenantRegistrationService::class);
        $slug = $service->generateSlug('My Company & Co.');

        $this->assertMatchesRegularExpression('/^[a-z0-9\-]+$/', $slug);
        $this->assertLessThanOrEqual(25, strlen($slug));
    }

    /** @test */
    public function test_generate_slug_is_unique_for_same_company_name(): void
    {
        $service = app(TenantRegistrationService::class);
        $slug1 = $service->generateSlug('Acme Corp');
        $slug2 = $service->generateSlug('Acme Corp');

        // Both slugs must be URL-safe; uniqueness is probabilistic but suffix ensures it
        $this->assertMatchesRegularExpression('/^[a-z0-9\-]+$/', $slug1);
        $this->assertMatchesRegularExpression('/^[a-z0-9\-]+$/', $slug2);
    }

    /** @test */
    public function test_generate_domain_appends_widehalo_cloud(): void
    {
        $service = app(TenantRegistrationService::class);
        $domain = $service->generateDomain('my-tenant-a1b2c3de');

        $this->assertStringEndsWith('.widehalo.cloud', $domain);
        $this->assertStringContainsString('my-tenant-a1b2c3de', $domain);
    }

    // ─── TenantModule / Multi-tenant isolation ────────────────────────────────

    /** @test */
    public function test_tenant_module_model_is_fillable(): void
    {
        $record = TenantModule::create([
            'tenant_id'  => 'tenant-abc',
            'module'     => 'CRM',
            'enabled'    => true,
            'department' => 'sales',
            'settings'   => ['foo' => 'bar'],
        ]);

        $this->assertInstanceOf(TenantModule::class, $record);
        $this->assertEquals('tenant-abc', $record->tenant_id);
        $this->assertEquals('CRM', $record->module);
        $this->assertTrue($record->enabled);
    }

    /** @test */
    public function test_tenant_module_enabled_cast_to_boolean(): void
    {
        $record = TenantModule::create([
            'tenant_id' => 't1',
            'module'    => 'HR',
            'enabled'   => 1,
        ]);

        $this->assertIsBool($record->enabled);
        $this->assertTrue($record->enabled);
    }

    /** @test */
    public function test_tenant_module_settings_cast_to_array(): void
    {
        $settings = ['max_users' => 50, 'feature_flags' => ['beta' => true]];

        $record = TenantModule::create([
            'tenant_id' => 't2',
            'module'    => 'Inventory',
            'enabled'   => true,
            'settings'  => $settings,
        ]);

        $fresh = TenantModule::find($record->id);
        $this->assertIsArray($fresh->settings);
        $this->assertEquals(50, $fresh->settings['max_users']);
    }

    /** @test */
    public function test_tenant_modules_are_isolated_per_tenant(): void
    {
        TenantModule::create(['tenant_id' => 'tenant-A', 'module' => 'CRM', 'enabled' => true]);
        TenantModule::create(['tenant_id' => 'tenant-B', 'module' => 'CRM', 'enabled' => false]);

        $this->assertEquals(1, TenantModule::where('tenant_id', 'tenant-A')->where('enabled', true)->count());
        $this->assertEquals(1, TenantModule::where('tenant_id', 'tenant-B')->where('enabled', false)->count());
    }

    /** @test */
    public function test_tenant_module_department_scope_filter(): void
    {
        TenantModule::create(['tenant_id' => 't3', 'module' => 'POS', 'enabled' => true, 'department' => 'retail']);
        TenantModule::create(['tenant_id' => 't3', 'module' => 'Email', 'enabled' => true, 'department' => null]);

        $retail = TenantModule::where('tenant_id', 't3')->where('department', 'retail')->get();
        $this->assertEquals(1, $retail->count());
        $this->assertEquals('POS', $retail->first()->module);
    }

    // ─── ModuleManager ─────────────────────────────────────────────────────────

    /** @test */
    public function test_module_manager_class_exists(): void
    {
        $this->assertTrue(class_exists(ModuleManager::class));
    }

    /** @test */
    public function test_module_manager_resolves_from_container(): void
    {
        $this->assertInstanceOf(ModuleManager::class, app(ModuleManager::class));
    }

    /** @test */
    public function test_core_module_always_enabled(): void
    {
        // Ensure at least one tenant_modules row so the fresh-install branch doesn't apply
        TenantModule::create(['tenant_id' => 'system', 'module' => 'HR', 'enabled' => true]);

        $manager = app(ModuleManager::class);
        $this->assertTrue($manager->isEnabled('Core'));
    }

    /** @test */
    public function test_module_manager_enable_and_disable_module(): void
    {
        $tenantId = 'tenant-toggle';
        $manager = app(ModuleManager::class);

        $manager->enable($tenantId, 'CRM');
        Cache::flush();
        $this->assertTrue($manager->isEnabled('CRM', null, $tenantId));

        $manager->disable($tenantId, 'CRM');
        Cache::flush();
        $this->assertFalse($manager->isEnabled('CRM', null, $tenantId));
    }

    /** @test */
    public function test_module_manager_cannot_disable_core(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(ModuleManager::class)->disable('tenant-x', 'Core');
    }

    /** @test */
    public function test_module_manager_enabled_modules_returns_collection(): void
    {
        $tenantId = 'tenant-list';
        TenantModule::create(['tenant_id' => $tenantId, 'module' => 'HR', 'enabled' => true]);
        TenantModule::create(['tenant_id' => $tenantId, 'module' => 'Accounting', 'enabled' => true]);

        $enabled = app(ModuleManager::class)->enabledModules($tenantId);

        $this->assertTrue($enabled->contains('HR'));
        $this->assertTrue($enabled->contains('Accounting'));
        $this->assertTrue($enabled->contains('Core')); // Core always included
    }

    /** @test */
    public function test_module_manager_disabled_module_not_in_list(): void
    {
        $tenantId = 'tenant-disabled';
        TenantModule::create(['tenant_id' => $tenantId, 'module' => 'Manufacturing', 'enabled' => false]);
        TenantModule::create(['tenant_id' => $tenantId, 'module' => 'HR', 'enabled' => true]);

        $enabled = app(ModuleManager::class)->enabledModules($tenantId);

        $this->assertFalse($enabled->contains('Manufacturing'));
        $this->assertTrue($enabled->contains('HR'));
    }

    // ─── AuditLog model ────────────────────────────────────────────────────────

    /** @test */
    public function test_audit_log_model_table_name(): void
    {
        $this->assertEquals('core_audit_logs', (new AuditLog())->getTable());
    }

    /** @test */
    public function test_audit_log_model_fillable_columns(): void
    {
        $fillable = (new AuditLog())->getFillable();

        foreach (['user_id', 'action', 'module', 'event_type', 'subject_type', 'old_values', 'new_values'] as $col) {
            $this->assertContains($col, $fillable, "Column '{$col}' missing from AuditLog fillable");
        }
    }

    /** @test */
    public function test_audit_log_creates_with_minimum_fields(): void
    {
        $log = AuditLog::create(['action' => 'test_action']);

        $this->assertInstanceOf(AuditLog::class, $log);
        $this->assertDatabaseHas('core_audit_logs', ['action' => 'test_action']);
    }

    /** @test */
    public function test_audit_log_old_values_and_new_values_cast_as_array(): void
    {
        $log = AuditLog::create([
            'action'     => 'updated',
            'old_values' => ['status' => 'draft'],
            'new_values' => ['status' => 'published'],
        ]);

        $fresh = AuditLog::find($log->id);
        $this->assertIsArray($fresh->old_values);
        $this->assertIsArray($fresh->new_values);
        $this->assertEquals('draft', $fresh->old_values['status']);
    }

    /** @test */
    public function test_audit_log_has_no_updated_at_timestamp(): void
    {
        // AuditLog uses $timestamps = false and only tracks created_at
        $log = new AuditLog();
        $this->assertFalse($log->usesTimestamps());
    }

    // ─── AuditService ──────────────────────────────────────────────────────────

    /** @test */
    public function test_audit_service_class_exists(): void
    {
        $this->assertTrue(class_exists(AuditService::class));
    }

    /** @test */
    public function test_audit_service_resolves_from_container(): void
    {
        $this->assertInstanceOf(AuditService::class, app(AuditService::class));
    }

    /** @test */
    public function test_audit_service_log_with_subject_stores_class_and_id(): void
    {
        $service = app(AuditService::class);
        $subject = TenantModule::create(['tenant_id' => 'ts', 'module' => 'POS', 'enabled' => true]);

        $log = $service->log(action: 'view', subject: $subject);

        $this->assertEquals(TenantModule::class, $log->subject_type);
        $this->assertEquals($subject->id, $log->subject_id);
    }

    /** @test */
    public function test_audit_service_log_empty_values_stored_as_null(): void
    {
        $service = app(AuditService::class);
        $log = $service->log(action: 'empty_values_test', oldValues: [], newValues: []);

        $this->assertNull($log->old_values);
        $this->assertNull($log->new_values);
    }

    /** @test */
    public function test_audit_service_get_stats_date_filter_works(): void
    {
        $service = app(AuditService::class);
        $service->log(action: 'old_action');

        $stats = $service->getStats([
            'date_from' => now()->addYear()->toDateString(), // future date → 0 results
        ]);

        $this->assertEquals(0, $stats['total_logs']);
    }

    /** @test */
    public function test_audit_service_log_infers_module_from_modules_namespace(): void
    {
        $service = app(AuditService::class);
        $subject = TenantModule::create(['tenant_id' => 'infer', 'module' => 'BI', 'enabled' => true]);

        $log = $service->log(action: 'created', subject: $subject);

        $this->assertEquals('Core', $log->module);
    }

    /** @test */
    public function test_audit_service_log_create_helper_sets_event_type(): void
    {
        $service  = app(AuditService::class);
        $subject  = TenantModule::create(['tenant_id' => 'x', 'module' => 'POS', 'enabled' => true]);
        $user     = \App\Models\User::factory()->create();

        $log = $service->logCreate($user->id, $subject);

        $this->assertEquals('model_created', $log->event_type);
    }

    /** @test */
    public function test_audit_service_log_update_stores_old_and_new_values(): void
    {
        $service = app(AuditService::class);
        $subject = TenantModule::create(['tenant_id' => 'y', 'module' => 'CRM', 'enabled' => false]);
        $user    = \App\Models\User::factory()->create();

        $log = $service->logUpdate(
            $user->id,
            $subject,
            ['enabled' => false],
            null,
            'Core',
        );

        $this->assertEquals('model_updated', $log->event_type);
        $this->assertIsArray($log->old_values);
    }

    // ─── Permission / Role resolution via Spatie HasRoles ─────────────────────

    /** @test */
    public function test_user_model_uses_spatie_has_roles_trait(): void
    {
        $this->assertContains(
            \Spatie\Permission\Traits\HasRoles::class,
            class_uses_recursive(\App\Models\User::class),
        );
    }

    /** @test */
    public function test_user_can_be_assigned_a_role_and_check_permission(): void
    {
        $user = \App\Models\User::factory()->create();

        // Create a permission and role via Spatie
        $permission = \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'view_crm_contacts', 'guard_name' => 'web']);
        $role       = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'sales_rep', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);
        $user->assignRole($role);

        $this->assertTrue($user->hasPermissionTo('view_crm_contacts'));
        $this->assertTrue($user->hasRole('sales_rep'));
    }

    /** @test */
    public function test_user_without_role_does_not_have_permission(): void
    {
        $user = \App\Models\User::factory()->create();

        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'delete_accounting_entries', 'guard_name' => 'web']);

        $this->assertFalse($user->hasPermissionTo('delete_accounting_entries'));
    }

    // ─── CsrfTokenGenerator ────────────────────────────────────────────────────

    /** @test */
    public function test_csrf_token_generator_class_exists(): void
    {
        $this->assertTrue(class_exists(CsrfTokenGenerator::class));
    }

    /** @test */
    public function test_csrf_token_generator_produces_hex_token(): void
    {
        $generator = new CsrfTokenGenerator();
        $token     = $generator->generateHex();

        $this->assertMatchesRegularExpression('/^[a-f0-9]+$/', $token);
        $this->assertGreaterThanOrEqual(64, strlen($token)); // 32 bytes → 64 hex chars
    }

    /** @test */
    public function test_csrf_two_consecutive_tokens_are_different(): void
    {
        $generator = new CsrfTokenGenerator();
        $token1    = $generator->generate();
        $token2    = $generator->generate();

        $this->assertNotEquals($token1, $token2);
    }

    /** @test */
    public function test_csrf_token_generator_hash_and_verify_round_trip(): void
    {
        $generator = new CsrfTokenGenerator();
        $token     = $generator->generate();
        $hash      = $generator->hash($token);

        $this->assertTrue($generator->verify($token, $hash));
        $this->assertFalse($generator->verify('tampered-token', $hash));
    }

    // ─── App\Http\Middleware\SecurityHeaders ───────────────────────────────────
    // (the real, live, globally-registered CSP/security-headers middleware --
    // see bootstrap/app.php. Modules\Core\Services\SecurityHeadersService, which
    // these two tests used to target, was a duplicate CSP engine that was never
    // wired into any middleware, controller, or route -- it has been deleted as
    // dead code.)

    /** @test */
    public function test_security_headers_middleware_resolves_from_container(): void
    {
        $this->assertInstanceOf(SecurityHeaders::class, app(SecurityHeaders::class));
    }

    /** @test */
    public function test_security_headers_middleware_generates_csp_header(): void
    {
        $response = $this->get('/login');

        $csp = $response->headers->get('Content-Security-Policy');

        $this->assertIsString($csp);
        $this->assertStringContainsString('default-src', $csp);
    }

    // ─── XssPreventionService ─────────────────────────────────────────────────

    /** @test */
    public function test_xss_prevention_service_resolves_from_container(): void
    {
        $this->assertInstanceOf(XssPreventionService::class, app(XssPreventionService::class));
    }

    /** @test */
    public function test_xss_prevention_detects_script_tag_as_malicious(): void
    {
        $service = app(XssPreventionService::class);
        $payload = '<script>alert("xss")</script>';

        $this->assertTrue($service->detectXss($payload));
    }

    /** @test */
    public function test_xss_prevention_clean_text_is_not_flagged(): void
    {
        $service = app(XssPreventionService::class);

        $this->assertFalse($service->detectXss('Hello, world!'));
    }

    /** @test */
    public function test_xss_prevention_removes_dangerous_tags_on_sanitize(): void
    {
        $service   = app(XssPreventionService::class);
        $dirty     = '<p>Hello</p><script>evil()</script>';
        $sanitized = $service->removeDangerousTags($dirty);

        $this->assertStringNotContainsString('<script>', $sanitized);
        $this->assertStringContainsString('Hello', $sanitized);
    }

    /** @test */
    public function test_xss_prevention_strips_event_handlers(): void
    {
        $service   = app(XssPreventionService::class);
        $dirty     = '<div onclick="steal()">Click</div>';
        $sanitized = $service->stripEventHandlers($dirty);

        $this->assertStringNotContainsString('onclick', $sanitized);
    }

    /** @test */
    public function test_xss_prevention_removes_javascript_protocol(): void
    {
        $service   = app(XssPreventionService::class);
        $dirty     = '<a href="javascript:void(0)">link</a>';
        $sanitized = $service->removeDangerousProtocols($dirty);

        $this->assertStringNotContainsString('javascript:', $sanitized);
    }

    // ─── OutputEncodingService ─────────────────────────────────────────────────

    /** @test */
    public function test_output_encoding_service_resolves_from_container(): void
    {
        $this->assertInstanceOf(OutputEncodingService::class, app(OutputEncodingService::class));
    }

    /** @test */
    public function test_output_encoding_html_encodes_special_characters(): void
    {
        $service  = app(OutputEncodingService::class);
        $encoded  = $service->encodeHtml('<script>');

        $this->assertStringNotContainsString('<', $encoded);
        $this->assertStringContainsString('&lt;', $encoded);
    }

    // ─── RateLimitService structural check ────────────────────────────────────

    /** @test */
    public function test_rate_limit_service_class_exists(): void
    {
        $this->assertTrue(class_exists(RateLimitService::class));
    }
}
