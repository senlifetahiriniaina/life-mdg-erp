<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\User;
use Modules\Achats\Models\PurchaseOrder;
use Modules\Achats\Models\Supplier;
use Modules\Achats\Services\ApprovalRoutingService;
use Modules\Validation\Models\ApprovalRequest;
use Modules\Validation\Models\ApprovalWorkflow;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Chantier 31 — re-confirmation of the purchase-order approval chain's 7
 * layers (route/contrôleur/vue/modèle/format de données/sécurité/RBAC),
 * per this session's empirical-execution methodology (real HTTP requests
 * against realistically seeded data, never just re-reading code).
 *
 * Headline finding fixed by this chantier — confirmed empirically via a
 * real `php artisan migrate:fresh --seed` + `php artisan tinker` before any
 * test was written: `ApprovalRoutingService::createDefaultWorkflows()` —
 * the only thing that ever populates a real
 * Modules\Validation\Models\ApprovalWorkflow/ApprovalRule/ApprovalHierarchy/
 * HierarchyLevel/LevelApprover set for `module_name='Achats'` — was never
 * called from anywhere in the real seed chain (`database/seeders/
 * DatabaseSeeder.php`), only from this module's own
 * ApprovalRoutingIntegrationTest.php, which calls it directly. On a fresh
 * install, `ApprovalWorkflow::where('module_name','Achats')->count()` was
 * 0, so `PurchaseOrderService::submitForApproval()` always took its
 * `if (! $workflow) { … skip … }` branch: no real `ApprovalRequest` was
 * ever created, no amount-based multi-tier escalation ever ran, and
 * `approve()`/`reject()` (already correctly RBAC-gated on
 * `achats.purchase-order.approve`/`.reject` since Chantier 8.5-light)
 * always finalized a PO in a single shot regardless of its amount. Fixed by
 * wiring the seeder into the real chain via the new
 * `Modules\Achats\Database\Seeders\AchatsDatabaseSeeder` (called from
 * `DatabaseSeeder.php`, right after `AccountingDatabaseSeeder`) — a pure
 * wiring fix, `createDefaultWorkflows()` itself was already idempotent
 * (`firstOrCreate` throughout) and needed no change.
 *
 * `Modules/Achats/tests/Feature/ApprovalRoutingIntegrationTest.php`
 * (pre-existing) already locks in the routing/escalation math itself via
 * direct service calls; this file instead re-exercises the same chain over
 * the real HTTP routes/policy/RBAC/web-layer surface a real user actually
 * hits, which that file does not cover.
 */
function poChainUser(string $suffix, string $role, ?Company $company = null): User
{
    if (Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }
    if (ApprovalWorkflow::where('module_name', 'Achats')->count() === 0) {
        app(ApprovalRoutingService::class)->createDefaultWorkflows();
    }

    $company ??= Company::create([
        'name' => "Chantier31 Achats Co {$suffix}",
        'currency' => 'MGA',
        'timezone' => 'Indian/Antananarivo',
    ]);

    Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole($role);

    return $user;
}

function poChainOrder(User $user, float $total = 75000): PurchaseOrder
{
    $supplier = Supplier::factory()->create(['company_id' => $user->company_id]);

    return PurchaseOrder::create([
        'po_number' => 'PO-'.uniqid(),
        'supplier_id' => $supplier->id,
        'status' => 'draft',
        'order_date' => now(),
        'currency' => 'USD',
        'subtotal' => $total,
        'tax_amount' => 0,
        'shipping_cost' => 0,
        'total' => $total,
        'company_id' => $user->company_id,
    ]);
}

test('a real seed run populates real, multi-tier Achats approval workflows (the headline fix)', function () {
    test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    test()->seed(\Modules\Achats\Database\Seeders\AchatsDatabaseSeeder::class);

    expect(ApprovalWorkflow::where('module_name', 'Achats')->count())->toBe(2);

    $standard = ApprovalWorkflow::where('name', 'Standard PO Approval')->firstOrFail();
    expect($standard->rules()->count())->toBe(3);
    expect($standard->rules()->orderBy('rule_order')->get()->pluck('condition_value')->all())
        ->toBe(['5000', '5000', '50000']);
});

test('submitting a large PO via the real HTTP route creates a real 3-level pending approval request', function () {
    $requester = poChainUser('A', 'purchasing-manager');
    $po = poChainOrder($requester, 75000);

    test()->actingAs($requester, 'sanctum')
        ->postJson("/api/v1/achats/purchase-orders/{$po->id}/submit")
        ->assertOk();

    expect($po->fresh()->status)->toBe('submitted');

    $request = ApprovalRequest::where('approvable_type', PurchaseOrder::class)
        ->where('approvable_id', $po->id)->first();

    expect($request)->not->toBeNull()
        ->and($request->total_levels)->toBe(3)
        ->and($request->current_level)->toBe(1)
        ->and($request->status)->toBe('pending');
});

test('a small PO routes to the single-level tier, not the 3-level one', function () {
    $requester = poChainUser('A2', 'purchasing-manager');
    $po = poChainOrder($requester, 2000);

    test()->actingAs($requester, 'sanctum')
        ->postJson("/api/v1/achats/purchase-orders/{$po->id}/submit")
        ->assertOk();

    $request = ApprovalRequest::where('approvable_type', PurchaseOrder::class)
        ->where('approvable_id', $po->id)->first();

    expect($request->total_levels)->toBe(1);
});

test('warehouse-operator passes the route gate but is denied by the policy on both approve and reject', function () {
    $requester = poChainUser('B', 'purchasing-manager');
    $po = poChainOrder($requester, 2000);
    test()->actingAs($requester, 'sanctum')->postJson("/api/v1/achats/purchase-orders/{$po->id}/submit")->assertOk();

    $operator = poChainUser('B', 'warehouse-operator', $requester->company);

    test()->actingAs($operator, 'sanctum')
        ->postJson("/api/v1/achats/purchase-orders/{$po->id}/approve")
        ->assertForbidden();

    test()->actingAs($operator, 'sanctum')
        ->postJson("/api/v1/achats/purchase-orders/{$po->id}/reject")
        ->assertForbidden();

    expect($po->fresh()->status)->toBe('submitted');
});

test('a purchasing-manager can approve a small PO via the real HTTP route, finalizing it with a real audit trail', function () {
    $requester = poChainUser('C', 'purchasing-manager');
    $po = poChainOrder($requester, 2000);
    test()->actingAs($requester, 'sanctum')->postJson("/api/v1/achats/purchase-orders/{$po->id}/submit")->assertOk();

    $manager = poChainUser('C', 'purchasing-manager', $requester->company);

    test()->actingAs($manager, 'sanctum')
        ->postJson("/api/v1/achats/purchase-orders/{$po->id}/approve")
        ->assertOk();

    $po->refresh();
    expect($po->status)->toBe('approved')
        ->and($po->approved_by)->toBe($manager->id);

    test()->assertDatabaseHas('validation_approval_actions', [
        'approver_id' => $manager->id,
        'action' => 'approved',
    ]);
});

test('a purchasing-manager from another company gets 404 (not 403) approving a PO they do not own', function () {
    $requester = poChainUser('D', 'purchasing-manager');
    $po = poChainOrder($requester, 2000);
    test()->actingAs($requester, 'sanctum')->postJson("/api/v1/achats/purchase-orders/{$po->id}/submit")->assertOk();

    $otherCompanyManager = poChainUser('D-other', 'purchasing-manager');

    test()->actingAs($otherCompanyManager, 'sanctum')
        ->postJson("/api/v1/achats/purchase-orders/{$po->id}/approve")
        ->assertNotFound();

    expect($po->fresh()->status)->toBe('submitted');
});

test('a real 3-level PO cannot be finalized by fewer than 3 approvers, each level reassigning the real approver', function () {
    // The requester is deliberately given 'warehouse-operator' — a role
    // that passes the route-level gate (submitForApproval() only needs the
    // permissive PurchaseOrderPolicy::update() ability) but is NOT one of
    // ApprovalRoutingService's 3 escalation-chain roles
    // (purchasing-manager/manager/admin). Achats\Services\
    // ApprovalRoutingResolver (Validation) resolves the level-1 approver by
    // role name across ALL users with that role, with no company scoping —
    // if the requester itself also held 'purchasing-manager', it could be
    // (and, confirmed empirically, was) picked as its own level-1 approver,
    // making this test's exact approver_id assertions non-deterministic.
    $requester = poChainUser('E', 'warehouse-operator');
    $company = $requester->company;
    $purchasingManager = poChainUser('E', 'purchasing-manager', $company);
    $manager = poChainUser('E', 'manager', $company);
    $admin = poChainUser('E', 'admin', $company);

    $po = poChainOrder($requester, 75000);
    test()->actingAs($requester, 'sanctum')->postJson("/api/v1/achats/purchase-orders/{$po->id}/submit")->assertOk();

    $request = ApprovalRequest::where('approvable_type', PurchaseOrder::class)
        ->where('approvable_id', $po->id)->first();
    expect($request->approver_id)->toBe($purchasingManager->id);

    // Level 1 — must NOT finalize, must advance and reassign to level 2.
    test()->actingAs($purchasingManager, 'sanctum')
        ->postJson("/api/v1/achats/purchase-orders/{$po->id}/approve")
        ->assertOk();

    $request->refresh();
    expect($po->fresh()->status)->toBe('submitted')
        ->and($request->status)->toBe('pending')
        ->and($request->current_level)->toBe(2)
        ->and($request->approver_id)->toBe($manager->id);

    // Level 2 — still not final, advances to level 3 (admin).
    test()->actingAs($manager, 'sanctum')
        ->postJson("/api/v1/achats/purchase-orders/{$po->id}/approve")
        ->assertOk();

    $request->refresh();
    expect($po->fresh()->status)->toBe('submitted')
        ->and($request->current_level)->toBe(3)
        ->and($request->approver_id)->toBe($admin->id);

    // Level 3 — only now does the request AND the PO finalize.
    test()->actingAs($admin, 'sanctum')
        ->postJson("/api/v1/achats/purchase-orders/{$po->id}/approve")
        ->assertOk();

    $request->refresh();
    expect($request->status)->toBe('approved')
        ->and($po->fresh()->status)->toBe('approved')
        ->and($po->fresh()->approved_by)->toBe($admin->id);
});

/**
 * Documented, not fixed (a real finding, deliberately left as a documented
 * gap rather than silently redefined — see this chantier's final report):
 * PurchaseOrderPolicy::approve()/reject() only check the coarse
 * `achats.purchase-order.approve`/`.reject` permission — never whether the
 * acting user actually holds the CURRENT level's assigned role/user (per
 * ApprovalHierarchy → HierarchyLevel → LevelApprover). Any of
 * purchasing-manager/manager/admin can act at ANY level of a multi-tier
 * chain, in any order — a manager can approve/reject a PO still sitting at
 * level 1 (nominally purchasing-manager's), and a purchasing-manager can
 * still act again after their own level-1 decision, even once the request
 * has already advanced to level 2. The `total_levels`/`current_level`
 * machinery still requires N distinct decisions before finalizing (a real
 * "N-of-the-qualifying-pool" control), it just doesn't enforce which
 * specific role acts at which specific step. Whether that stricter
 * enforcement is actually wanted is a product decision, not an
 * unambiguous defect, so it's asserted here as the real current behavior
 * rather than silently assumed away or force-changed.
 */
test('a purchasing-manager can act again on a chain even after it has already advanced past their own level', function () {
    $requester = poChainUser('E2', 'warehouse-operator');
    $company = $requester->company;
    $purchasingManager = poChainUser('E2', 'purchasing-manager', $company);
    poChainUser('E2', 'manager', $company);

    $po = poChainOrder($requester, 75000);
    test()->actingAs($requester, 'sanctum')->postJson("/api/v1/achats/purchase-orders/{$po->id}/submit")->assertOk();

    test()->actingAs($purchasingManager, 'sanctum')
        ->postJson("/api/v1/achats/purchase-orders/{$po->id}/approve")
        ->assertOk();

    $request = ApprovalRequest::where('approvable_type', PurchaseOrder::class)
        ->where('approvable_id', $po->id)->first();
    expect($request->current_level)->toBe(2);

    // Level 1's own approver, whose turn has already passed, is still
    // allowed through the Policy to reject the (now level-2) request.
    test()->actingAs($purchasingManager, 'sanctum')
        ->postJson("/api/v1/achats/purchase-orders/{$po->id}/reject")
        ->assertOk();

    expect($po->fresh()->status)->toBe('rejected');
});

test('rejecting a PO via the real HTTP route stops the chain and records the real rejector', function () {
    $requester = poChainUser('F', 'purchasing-manager');
    $po = poChainOrder($requester, 2000);
    test()->actingAs($requester, 'sanctum')->postJson("/api/v1/achats/purchase-orders/{$po->id}/submit")->assertOk();

    $manager = poChainUser('F', 'purchasing-manager', $requester->company);

    test()->actingAs($manager, 'sanctum')
        ->postJson("/api/v1/achats/purchase-orders/{$po->id}/reject", ['reason' => 'Budget insuffisant'])
        ->assertOk();

    $po->refresh();
    expect($po->status)->toBe('rejected')
        ->and($po->rejected_by)->toBe($manager->id);

    $request = ApprovalRequest::where('approvable_type', PurchaseOrder::class)
        ->where('approvable_id', $po->id)->first();
    expect($request->status)->toBe('rejected');
});

test('editing a PO via PATCH (matching PurchaseOrders/Form.vue) succeeds, confirming the earlier PATCH-vs-PUT fix is still live', function () {
    $user = poChainUser('G', 'purchasing-manager');
    $po = poChainOrder($user, 2000);

    test()->actingAs($user, 'sanctum')
        ->patchJson("/api/v1/achats/purchase-orders/{$po->id}", ['notes' => 'Mis à jour via PATCH'])
        ->assertOk();

    expect($po->fresh()->notes)->toBe('Mis à jour via PATCH');
});

test('the real web Show page is reachable and server-renders the real approval chain + can_approve for an eligible approver', function () {
    $user = poChainUser('H', 'purchasing-manager');
    $po = poChainOrder($user, 75000);
    test()->actingAs($user, 'sanctum')->postJson("/api/v1/achats/purchase-orders/{$po->id}/submit")->assertOk();

    // component(..., false) skips inertia-laravel's own page-exists finder,
    // which doesn't understand this app's custom module-prefixed resolve()
    // — same established workaround as Chantier22DepositBalanceTest.php.
    test()->actingAs($user)
        ->get("/purchase-orders/{$po->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Achats/PurchaseOrders/Show', false)
            ->where('purchaseOrder.id', $po->id)
            ->where('purchaseOrder.approval.total_levels', 3)
            ->where('purchaseOrder.approval.current_level', 1)
            ->where('purchaseOrder.can_approve', true)
        );
});

test('the real web Show page reports can_approve=false for warehouse-operator', function () {
    $owner = poChainUser('I', 'purchasing-manager');
    $po = poChainOrder($owner, 2000);
    test()->actingAs($owner, 'sanctum')->postJson("/api/v1/achats/purchase-orders/{$po->id}/submit")->assertOk();

    $operator = poChainUser('I', 'warehouse-operator', $owner->company);

    test()->actingAs($operator)
        ->get("/purchase-orders/{$po->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Achats/PurchaseOrders/Show', false)
            ->where('purchaseOrder.can_approve', false)
        );
});

test('the web Show page 404s (server-rendered, not just the API) for a user from another company', function () {
    $owner = poChainUser('J', 'purchasing-manager');
    $po = poChainOrder($owner, 2000);

    $other = poChainUser('J-other', 'purchasing-manager');

    test()->actingAs($other)->get("/purchase-orders/{$po->id}")->assertNotFound();
});
