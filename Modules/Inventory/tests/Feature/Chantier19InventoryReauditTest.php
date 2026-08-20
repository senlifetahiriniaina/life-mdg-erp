<?php

use App\Models\Company;
use App\Models\User;

/**
 * Chantier 19 Lot 4 (Inventory) re-audit via empirical execution — this
 * module's own Chantier 10 pass only checked Policy registration/authorize()
 * calls, not tenant scoping at the query level, and came back "an outlier,
 * zero findings" compared to every other module. Re-checking with fresh eyes
 * (and a real cross-company HTTP request, not just code reading) found the
 * one real gap this module did have: ChannelController's marketplace-channel
 * connections silently collapsed into one shared tenant_id=0 bucket via the
 * well-documented phantom `users.tenant_id` column, and connect() accepted a
 * client-supplied `company_id` straight into the create() call — a real IDOR
 * independent of the tenant-collapse bug. Both fixed to derive the tenant
 * boundary from the real `users.company_id` column server-side only.
 *
 * A much larger, module-wide finding — Inventory has literally zero
 * company/tenant scoping anywhere else (Product/Category/Warehouse/Supplier/
 * StockMovement/Lot/PickingOrder/PurchaseOrder/TransferOrder/Shipment/RMA/…
 * confirmed via grep to have zero company_id/tenant_id filter in any
 * controller or service) — is documented, not fixed, in this pass's final
 * report, matching the established "genuinely large, separately-scoped
 * effort" precedent already used for CRM's (Lot 1) and HR's (Lot 2) own
 * module-wide tenant-isolation gaps of the same shape and scale.
 */
describe('Chantier 19 Lot 4 — Inventory ChannelController tenant isolation', function () {
    test('a marketplace channel connected by one company is invisible to another company', function () {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        if (\Spatie\Permission\Models\Permission::count() === 0) {
            $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        }
        $userA = User::factory()->create(['company_id' => $companyA->id]);
        $userA->assignRole('admin');
        $userB = User::factory()->create(['company_id' => $companyB->id]);
        $userB->assignRole('admin');

        $connect = $this->actingAs($userA, 'sanctum')
            ->postJson('/api/v1/inventory/channels/amazon/connect', [
                'name' => 'Amazon FR — Company A',
                'config' => ['api_key' => 'secret-a'],
            ]);
        $connect->assertStatus(201);
        $channelId = $connect->json('data.id');

        // Confirmed empirically before the fix: this response included every
        // company's channels because ChannelController::index() filtered on
        // the phantom `users.tenant_id` column (never populated), which
        // always resolved to the same shared 0 bucket for everyone.
        $listB = $this->actingAs($userB, 'sanctum')->getJson('/api/v1/inventory/channels');
        $listB->assertStatus(200);
        expect(collect($listB->json('data'))->pluck('id'))->not->toContain($channelId);

        $listA = $this->actingAs($userA, 'sanctum')->getJson('/api/v1/inventory/channels');
        expect(collect($listA->json('data'))->pluck('id'))->toContain($channelId);

        // sync()/status() previously had zero scoping at all (any authenticated
        // user of any company could sync/read another company's channel by id).
        $this->actingAs($userB, 'sanctum')
            ->postJson("/api/v1/inventory/channels/{$channelId}/sync")
            ->assertStatus(404);
        $this->actingAs($userA, 'sanctum')
            ->postJson("/api/v1/inventory/channels/{$channelId}/sync")
            ->assertStatus(200);
    });

    test('connect() no longer trusts a client-supplied company_id', function () {
        $company = Company::factory()->create();
        if (\Spatie\Permission\Models\Permission::count() === 0) {
            $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        }
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->assignRole('admin');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/inventory/channels/ebay/connect', [
                'name' => 'eBay Store',
                'config' => ['api_key' => 'x'],
                // A malicious/naive client could previously tag the new
                // channel with any company_id it liked — this is now ignored
                // server-side in favour of the caller's real company_id.
                'company_id' => 999999,
            ]);

        $response->assertStatus(201);
        expect($response->json('data.company_id'))->toBe($company->id);
        expect($response->json('data.company_id'))->not->toBe(999999);
    });
});
