<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Tenant;
use Tests\TestCase;

/**
 * Chantier 19 Lot 3: real, previously-undocumented active-breakage bug
 * found via empirical execution — resources/js/Pages/Admin/
 * TenantExchanges/Index.vue (the only real caller of
 * POST /api/v1/core/exchanges, confirmed via a repo-wide grep) has always
 * sent `target_tenant_id` in its request body, but
 * TenantExchangeController::store() validated `target_tenant` — a real
 * field-name mismatch, not a hypothetical one, confirmed by replaying the
 * frontend's exact request body over the real HTTP route and getting a 422
 * before the fix. Fixed to accept either key, so an already-shipped caller
 * matching the old name keeps working too.
 */
class TenantExchangeControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_accepts_the_real_frontend_payload_shape()
    {
        $user = $this->actingAsUser('admin');

        $target = Tenant::create([
            'id' => 'target-tenant-for-exchange-test',
            'slug' => 'target-tenant-for-exchange-test',
            'name' => 'Target Co',
            'company_name' => 'Target Co',
            'country_code' => 'MG',
            'currency' => 'MGA',
            'industry' => 'general',
            'plan' => 'starter',
            'status' => 'active',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/core/exchanges', [
            'target_tenant_id' => $target->id,
            'exchange_type' => 'product_share',
            'message' => null,
            'payload' => ['foo' => 'bar'],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('target_tenant_id', $target->id);
    }

    public function test_store_still_accepts_the_original_target_tenant_key()
    {
        $user = $this->actingAsUser('admin');

        $target = Tenant::create([
            'id' => 'target-tenant-legacy-key-test',
            'slug' => 'target-tenant-legacy-key-test',
            'name' => 'Legacy Target Co',
            'company_name' => 'Legacy Target Co',
            'country_code' => 'MG',
            'currency' => 'MGA',
            'industry' => 'general',
            'plan' => 'starter',
            'status' => 'active',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/core/exchanges', [
            'target_tenant' => $target->id,
            'exchange_type' => 'catalog_share',
            'payload' => ['foo' => 'bar'],
        ]);

        $response->assertCreated();
    }
}
