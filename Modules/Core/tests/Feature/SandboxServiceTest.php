<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Modules\Core\Models\Sandbox;
use Modules\Core\Models\Tenant;
use Modules\Core\Services\SandboxService;
use Modules\Core\Services\TenantManagerService;
use Tests\TestCase;

/**
 * SandboxServiceTest — Tenant Sandbox Environments (Item #19)
 *
 * Tests the full lifecycle of a tenant sandbox: creation, expiry, reset,
 * deletion, and scheduled expiry cleanup.
 *
 * TenantManagerService is mocked so tests do not trigger real DB provisioning.
 */
class SandboxServiceTest extends TestCase
{
    use RefreshDatabase;

    private SandboxService $service;

    /** @var TenantManagerService&MockInterface */
    private TenantManagerService $tenantManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantManager = Mockery::mock(TenantManagerService::class);
        $this->service       = new SandboxService($this->tenantManager);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Create and persist a minimal Tenant record (string PK).
     */
    private function makeParentTenant(array $overrides = []): Tenant
    {
        $id = uniqid('t', false);

        return Tenant::create(array_merge([
            'id'           => $id,
            'slug'         => 'acme-' . $id,
            'name'         => 'ACME Corp',
            'company_name' => 'ACME Corp',
            'country_code' => 'SN',
            'currency'     => 'XOF',
            'locale'       => 'fr',
            'timezone'     => 'Africa/Dakar',
            'industry'     => 'retail',
            'plan'         => 'professional',
            'status'       => 'active',
            'is_active'    => true,
        ], $overrides));
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    public function test_can_clone_tenant_creates_sandbox_record(): void
    {
        $parent  = $this->makeParentTenant();
        $sandbox = $this->service->cloneTenant((int) $parent->id, 'My Sandbox');

        $this->assertInstanceOf(Sandbox::class, $sandbox);
        $this->assertDatabaseHas('sandboxes', [
            'id'               => $sandbox->id,
            'parent_tenant_id' => $parent->id,
            'name'             => 'My Sandbox',
            'status'           => Sandbox::STATUS_ACTIVE,
        ]);
    }

    public function test_cloned_tenant_is_marked_as_sandbox(): void
    {
        $parent  = $this->makeParentTenant();
        $sandbox = $this->service->cloneTenant((int) $parent->id, 'Test Sandbox');

        $sandboxTenant = Tenant::find($sandbox->tenant_id);

        $this->assertNotNull($sandboxTenant);
        $this->assertStringContainsString('[SANDBOX]', $sandboxTenant->name);
        $this->assertTrue((bool) ($sandboxTenant->settings['is_sandbox'] ?? false));
        $this->assertSame((string) $parent->id, $sandboxTenant->settings['parent_tenant_id'] ?? null);
    }

    public function test_cloned_tenant_copies_country_and_currency(): void
    {
        $parent  = $this->makeParentTenant([
            'country_code' => 'CM',
            'currency'     => 'XAF',
            'locale'       => 'fr',
            'timezone'     => 'Africa/Douala',
            'industry'     => 'manufacturing',
        ]);

        $sandbox = $this->service->cloneTenant((int) $parent->id, 'CM Sandbox');

        $sandboxTenant = Tenant::find($sandbox->tenant_id);

        $this->assertSame('CM', $sandboxTenant->country_code);
        $this->assertSame('XAF', $sandboxTenant->currency);
        $this->assertSame('fr', $sandboxTenant->locale);
        $this->assertSame('Africa/Douala', $sandboxTenant->timezone);
        $this->assertSame('manufacturing', $sandboxTenant->industry);
    }

    public function test_cloned_tenant_has_no_real_data(): void
    {
        $parent  = $this->makeParentTenant();
        $sandbox = $this->service->cloneTenant((int) $parent->id, 'Empty Sandbox');

        $sandboxTenant = Tenant::find($sandbox->tenant_id);

        // Sandbox tenant must be in trial state (not active) with no real data
        $this->assertSame('trial', $sandboxTenant->status);
        // owner_id is not copied — sandbox has no owner by default
        $this->assertNull($sandboxTenant->owner_id);
    }

    public function test_sandbox_has_30_day_expiry(): void
    {
        $parent  = $this->makeParentTenant();
        $sandbox = $this->service->cloneTenant((int) $parent->id, 'Expiry Test');

        $this->assertNotNull($sandbox->expires_at);
        // expires_at should be approximately 30 days from now (within 1 min)
        $expectedExpiry = now()->addDays(Sandbox::EXPIRY_DAYS);
        $this->assertTrue(
            $sandbox->expires_at->between(
                $expectedExpiry->clone()->subMinute(),
                $expectedExpiry->clone()->addMinute(),
            )
        );
    }

    public function test_can_delete_sandbox(): void
    {
        $parent  = $this->makeParentTenant();
        $sandbox = $this->service->cloneTenant((int) $parent->id, 'To Delete');

        $result = $this->service->deleteSandbox($sandbox->id);

        $this->assertTrue($result);

        // The row should be soft-deleted with status=deleted
        $this->assertSoftDeleted('sandboxes', ['id' => $sandbox->id]);

        $deleted = Sandbox::withTrashed()->find($sandbox->id);
        $this->assertSame(Sandbox::STATUS_DELETED, $deleted->status);
    }

    public function test_can_reset_sandbox_extends_expiry(): void
    {
        $parent  = $this->makeParentTenant();
        $sandbox = $this->service->cloneTenant((int) $parent->id, 'To Reset');

        // Simulate an almost-expired sandbox
        $sandbox->update([
            'expires_at' => now()->addHour(),
            'status'     => Sandbox::STATUS_ACTIVE,
        ]);

        $reset = $this->service->resetSandbox($sandbox->id);

        $this->assertSame(Sandbox::STATUS_ACTIVE, $reset->status);

        $expectedExpiry = now()->addDays(Sandbox::EXPIRY_DAYS);
        $this->assertTrue(
            $reset->expires_at->between(
                $expectedExpiry->clone()->subMinute(),
                $expectedExpiry->clone()->addMinute(),
            )
        );
    }

    public function test_expire_overdue_marks_expired_sandboxes(): void
    {
        $parent = $this->makeParentTenant();

        // Sandbox 1: already expired
        $s1 = $this->service->cloneTenant((int) $parent->id, 'Expired Sandbox');
        $s1->update(['expires_at' => now()->subDay()]);

        // Sandbox 2: still active
        $s2 = $this->service->cloneTenant((int) $parent->id, 'Active Sandbox');
        $s2->update(['expires_at' => now()->addDay()]);

        $count = $this->service->expireOverdue();

        $this->assertSame(1, $count);
        $this->assertDatabaseHas('sandboxes', ['id' => $s1->id, 'status' => Sandbox::STATUS_EXPIRED]);
        $this->assertDatabaseHas('sandboxes', ['id' => $s2->id, 'status' => Sandbox::STATUS_ACTIVE]);
    }

    public function test_list_by_parent_returns_only_active_sandboxes(): void
    {
        $parent = $this->makeParentTenant();

        $active  = $this->service->cloneTenant((int) $parent->id, 'Active');
        $expired = $this->service->cloneTenant((int) $parent->id, 'Expired');
        $expired->update(['expires_at' => now()->subDay(), 'status' => Sandbox::STATUS_EXPIRED]);

        $results = $this->service->listByParent((int) $parent->id);

        $this->assertCount(1, $results);
        $this->assertSame($active->id, $results->first()->id);
    }
}
