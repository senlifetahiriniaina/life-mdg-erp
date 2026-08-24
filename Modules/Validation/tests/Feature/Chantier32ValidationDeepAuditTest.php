<?php

declare(strict_types=1);

/**
 * Chantier 32.7 — 14-layer deep audit of Modules\Validation (layers 8-14;
 * layers 1-7 already re-verified thoroughly by Chantier 8.5sv/19 Lot 3/31,
 * re-confirmed still holding rather than re-tested from scratch here). Same
 * empirical-execution discipline as every prior chantier in this session:
 * every claim below is backed by a real HTTP request or a real service call
 * against real seeded data, never a re-read of the code.
 *
 * Real bugs found and fixed by this chantier, each locked in below:
 *
 *  1. (Layer 6/8 security+business-validation) ApprovalRequestController::
 *     store()'s `approvable_type` validation used
 *     `array_keys(Relation::morphMap())` — the ENTIRE global morph map
 *     (invoice, purchase_order, contact, product, sales_order, project,
 *     shipment, employee — Helpdesk registers 6 more aliases than
 *     Validation's own 2), not this module's own allowlist its code comment
 *     claimed. Confirmed empirically before the fix that
 *     `approvable_type: 'employee'` passed validation.
 *  2. (Layer 6 IDOR) store() had zero ownership check on the `approvable`
 *     it resolves — any admin/manager of ANY company could wire an
 *     ApprovalRequest to another company's PurchaseOrder by id.
 *  3. (Layer 8 business validation) delegateApproval() reassigned
 *     approver_id to whatever `to_user_id` the request body named, with no
 *     check the delegate belongs to the request's own company at all.
 *  4. (Layer 11 CORE integration) NotifyApprovalParticipants's action_url
 *     pointed at "/validation/approval-requests/{id}", a URL that has never
 *     existed — the real route (routes/web.php has NO prefix) is
 *     "/approval-requests/{id}". Every approval notification's "view" link
 *     404'd.
 *  5. (Layer 10 relational/data-format) ApprovalRuleController::store()/
 *     update() never validated `hierarchy_id`, so Workflows/Builder.vue's
 *     "Approver hierarchy" dropdown was silently dropped on every real rule
 *     save — ApprovalRoutingResolver::resolveHierarchy() always fell back
 *     to the module's generic hierarchy instead of the admin's real choice.
 *  6. (Layer 13 AI) 'Validation' was never registered in
 *     AiContextualAssistantService::supportedModules()/its fallback map,
 *     AND none of the module's 5 real Vue pages ever called
 *     useAiAssistant() — the exact double-gap Chantier 30 found and fixed
 *     for 'Strategy'.
 *
 * Layers re-confirmed still holding (not bugs, documented as verified):
 *  - Layer 8: a level cannot be skipped via a crafted HTTP request — the
 *    server recomputes current_level/total_levels from its own state,
 *    accepting only an optional `comment` in the request body.
 *  - Layer 9: ValidationEngine::createRuleSet()/validateWithRuleSet()/
 *    hasCircularDependency() are genuinely reachable end-to-end through
 *    real HTTP routes, not just routed-but-orphaned.
 */

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Achats\Models\PurchaseOrder;
use Modules\Achats\Models\Supplier;
use Modules\Achats\Services\ApprovalRoutingService;
use Modules\Achats\Services\PurchaseOrderService;
use Modules\Accounting\Models\Invoice;
use Modules\Validation\Models\ApprovalHierarchy;
use Modules\Validation\Models\ApprovalRequest;
use Modules\Validation\Models\ApprovalWorkflow;
use Modules\Validation\Models\ValidationRule;
use Modules\Validation\Services\ApprovalRequestService;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function c327User(?Company $company, string $role): User
{
    if (\Spatie\Permission\Models\Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }
    $user = User::factory()->create(['company_id' => $company?->id]);
    $user->assignRole($role);

    return $user;
}

function c327Supplier(): Supplier
{
    return Supplier::create([
        'code' => 'SUP-C327',
        'name' => 'Chantier 32.7 Supplier',
        'email' => 'supplier-c327@example.test',
        'is_active' => true,
    ]);
}

// ─────────────────────────────────────────────────────────────────────────
// Layer 6/8 — approvable_type allowlist (fix #1)
// ─────────────────────────────────────────────────────────────────────────

test('POST approval-requests rejects an approvable_type outside Validation\'s own allowlist, even though it IS a globally registered morph-map alias', function () {
    $company = Company::factory()->create();
    $admin = c327User($company, 'admin');
    $workflow = ApprovalWorkflow::create(['name' => 'WF-alias', 'module_name' => 'HR', 'is_active' => true, 'created_by' => $admin->id]);

    // 'employee' is a REAL alias in the global Relation::morphMap() —
    // registered by Modules\Helpdesk\Providers\HelpdeskServiceProvider, not
    // by this module — confirming the fix scopes to Validation's own 2
    // types, not the app-wide merged map.
    expect(array_key_exists('employee', \Illuminate\Database\Eloquent\Relations\Relation::morphMap()))->toBeTrue();

    $response = test()->actingAs($admin, 'sanctum')->postJson('/api/v1/validation/approval-requests', [
        'workflow_id' => $workflow->id,
        'approvable_type' => 'employee',
        'approvable_id' => 1,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('approvable_type');
});

test('POST approval-requests still accepts the module\'s own real "invoice"/"purchase_order" aliases', function () {
    $company = Company::factory()->create();
    $admin = c327User($company, 'admin');
    $invoice = Invoice::factory()->create();
    $workflow = ApprovalWorkflow::create(['name' => 'WF-invoice-ok', 'module_name' => 'Accounting', 'is_active' => true, 'created_by' => $admin->id]);

    $response = test()->actingAs($admin, 'sanctum')->postJson('/api/v1/validation/approval-requests', [
        'workflow_id' => $workflow->id,
        'approvable_type' => 'invoice',
        'approvable_id' => $invoice->id,
    ]);

    $response->assertStatus(201);
});

// ─────────────────────────────────────────────────────────────────────────
// Layer 6 — cross-tenant IDOR on store() (fix #2)
// ─────────────────────────────────────────────────────────────────────────

test('POST approval-requests refuses to wire an ApprovalRequest to another company\'s PurchaseOrder', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $adminB = c327User($companyB, 'admin');

    $poA = PurchaseOrder::factory()->create(['company_id' => $companyA->id]);
    $workflow = ApprovalWorkflow::create(['name' => 'WF-idor', 'module_name' => 'Achats', 'is_active' => true, 'created_by' => $adminB->id]);

    $response = test()->actingAs($adminB, 'sanctum')->postJson('/api/v1/validation/approval-requests', [
        'workflow_id' => $workflow->id,
        'approvable_type' => 'purchase_order',
        'approvable_id' => $poA->id,
    ]);

    $response->assertStatus(404);
    expect(ApprovalRequest::where('approvable_id', $poA->id)->exists())->toBeFalse();
});

test('POST approval-requests still succeeds for a PurchaseOrder that IS the requester\'s own company', function () {
    $companyA = Company::factory()->create();
    $adminA = c327User($companyA, 'admin');

    $poA = PurchaseOrder::factory()->create(['company_id' => $companyA->id]);
    $workflow = ApprovalWorkflow::create(['name' => 'WF-own-company', 'module_name' => 'Achats', 'is_active' => true, 'created_by' => $adminA->id]);

    $response = test()->actingAs($adminA, 'sanctum')->postJson('/api/v1/validation/approval-requests', [
        'workflow_id' => $workflow->id,
        'approvable_type' => 'purchase_order',
        'approvable_id' => $poA->id,
    ]);

    $response->assertStatus(201);
});

test('POST approval-requests still succeeds against an Invoice (no company_id column at all — the check is correctly a no-op, not a false negative)', function () {
    $company = Company::factory()->create();
    $admin = c327User($company, 'admin');
    $invoice = Invoice::factory()->create();
    $workflow = ApprovalWorkflow::create(['name' => 'WF-invoice-noop', 'module_name' => 'Accounting', 'is_active' => true, 'created_by' => $admin->id]);

    expect(\Illuminate\Support\Facades\Schema::hasColumn($invoice->getTable(), 'company_id'))->toBeFalse();

    $response = test()->actingAs($admin, 'sanctum')->postJson('/api/v1/validation/approval-requests', [
        'workflow_id' => $workflow->id,
        'approvable_type' => 'invoice',
        'approvable_id' => $invoice->id,
    ]);

    $response->assertStatus(201);
});

test('super-admin is exempt from the company check and can wire a request to another company\'s PurchaseOrder', function () {
    $companyA = Company::factory()->create();
    $superAdmin = c327User(null, 'super-admin');

    $poA = PurchaseOrder::factory()->create(['company_id' => $companyA->id]);
    $workflow = ApprovalWorkflow::create(['name' => 'WF-superadmin', 'module_name' => 'Achats', 'is_active' => true, 'created_by' => $superAdmin->id]);

    $response = test()->actingAs($superAdmin, 'sanctum')->postJson('/api/v1/validation/approval-requests', [
        'workflow_id' => $workflow->id,
        'approvable_type' => 'purchase_order',
        'approvable_id' => $poA->id,
    ]);

    $response->assertStatus(201);
});

// ─────────────────────────────────────────────────────────────────────────
// Layer 8 — delegate() to an arbitrary user (fix #3)
// ─────────────────────────────────────────────────────────────────────────

test('delegate() refuses to hand a request to a user outside the request\'s own company', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $adminA = c327User($companyA, 'admin');
    $outsider = c327User($companyB, 'employee');

    $poA = PurchaseOrder::factory()->create(['company_id' => $companyA->id]);
    $workflow = ApprovalWorkflow::create(['name' => 'WF-delegate-idor', 'module_name' => 'Achats', 'is_active' => true, 'created_by' => $adminA->id]);
    $request = app(ApprovalRequestService::class)->createApprovalRequest($poA, $workflow, $adminA);
    $request->update(['status' => 'pending', 'approver_id' => $adminA->id]);

    $response = test()->actingAs($adminA, 'sanctum')
        ->postJson("/api/v1/validation/approval-requests/{$request->id}/delegate", [
            'to_user_id' => $outsider->id,
        ]);

    $response->assertStatus(422);
    expect($request->fresh()->approver_id)->toBe($adminA->id);
});

test('delegate() still succeeds when the delegate is in the same company', function () {
    $companyA = Company::factory()->create();
    $adminA = c327User($companyA, 'admin');
    $colleague = c327User($companyA, 'employee');

    $poA = PurchaseOrder::factory()->create(['company_id' => $companyA->id]);
    $workflow = ApprovalWorkflow::create(['name' => 'WF-delegate-ok', 'module_name' => 'Achats', 'is_active' => true, 'created_by' => $adminA->id]);
    $request = app(ApprovalRequestService::class)->createApprovalRequest($poA, $workflow, $adminA);
    $request->update(['status' => 'pending', 'approver_id' => $adminA->id]);

    $response = test()->actingAs($adminA, 'sanctum')
        ->postJson("/api/v1/validation/approval-requests/{$request->id}/delegate", [
            'to_user_id' => $colleague->id,
        ]);

    $response->assertStatus(200);
    expect($request->fresh()->approver_id)->toBe($colleague->id);
});

test('super-admin can delegate a request across companies', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $superAdmin = c327User(null, 'super-admin');
    $outsider = c327User($companyB, 'employee');

    $poA = PurchaseOrder::factory()->create(['company_id' => $companyA->id]);
    $workflow = ApprovalWorkflow::create(['name' => 'WF-delegate-super', 'module_name' => 'Achats', 'is_active' => true, 'created_by' => $superAdmin->id]);
    $request = app(ApprovalRequestService::class)->createApprovalRequest($poA, $workflow, $superAdmin);
    $request->update(['status' => 'pending', 'approver_id' => $superAdmin->id]);

    $response = test()->actingAs($superAdmin, 'sanctum')
        ->postJson("/api/v1/validation/approval-requests/{$request->id}/delegate", [
            'to_user_id' => $outsider->id,
        ]);

    $response->assertStatus(200);
    expect($request->fresh()->approver_id)->toBe($outsider->id);
});

// ─────────────────────────────────────────────────────────────────────────
// Layer 8 — a level cannot be skipped or approved out of order via a
// crafted request; re-confirmed empirically through Validation's OWN
// generic HTTP endpoint (not PurchaseOrderController), using a real Achats
// 3-tier hierarchy (app(ApprovalRoutingService)->createDefaultWorkflows()
// — calling Achats' own real service, not editing Achats code).
// ─────────────────────────────────────────────────────────────────────────

test('approving via the generic endpoint advances exactly one level at a time, never skipping ahead, for a real 3-level Achats workflow', function () {
    app(ApprovalRoutingService::class)->createDefaultWorkflows();
    Role::firstOrCreate(['name' => 'purchasing-manager', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $purchasingManager = User::factory()->create();
    $purchasingManager->assignRole('purchasing-manager');
    $manager = User::factory()->create();
    $manager->assignRole('manager');
    $adminApprover = User::factory()->create();
    $adminApprover->assignRole('admin');

    $supplier = c327Supplier();
    $po = PurchaseOrder::create([
        'po_number' => 'PO-C327-1',
        'supplier_id' => $supplier->id,
        'status' => 'draft',
        'order_date' => now()->toDateString(),
        'currency' => 'USD',
        'subtotal' => 75000,
        'tax_amount' => 0,
        'shipping_cost' => 0,
        'total' => 75000, // >= 50000 -> the real 3-tier rule
    ]);
    $requester = User::factory()->create();

    app(PurchaseOrderService::class)->submitForApproval($po, $requester);

    $request = ApprovalRequest::where('approvable_type', PurchaseOrder::class)
        ->where('approvable_id', $po->id)
        ->firstOrFail();

    expect($request->total_levels)->toBe(3);
    expect($request->current_level)->toBe(1);
    expect($request->approver_id)->toBe($purchasingManager->id);

    // Level 1 -> only ONE call, hitting Validation's own generic endpoint
    // directly (not the Achats-specific PurchaseOrderController) — the
    // level-advance logic lives entirely server-side in
    // ApprovalRequestService::approveRequest(), reading only
    // current_level/total_levels off the DB row; the request body accepts
    // no field that could influence which level gets recorded.
    test()->actingAs($purchasingManager, 'sanctum')
        ->postJson("/api/v1/validation/approval-requests/{$request->id}/approve", ['comment' => 'OK niveau 1'])
        ->assertOk();

    $request->refresh();
    expect($request->status)->toBe('pending'); // NOT finalized — 2 levels remain
    expect($request->current_level)->toBe(2);   // advanced by exactly 1
    expect($request->approver_id)->toBe($manager->id);

    // The level-1 approver (no longer the assigned approver_id) can no
    // longer approve at level 2 — confirms the policy re-checks the LIVE
    // approver_id, not a snapshot from before the level advanced.
    test()->actingAs($purchasingManager, 'sanctum')
        ->postJson("/api/v1/validation/approval-requests/{$request->id}/approve", ['comment' => 'trying again'])
        ->assertForbidden();

    test()->actingAs($manager, 'sanctum')
        ->postJson("/api/v1/validation/approval-requests/{$request->id}/approve", ['comment' => 'OK niveau 2'])
        ->assertOk();

    $request->refresh();
    expect($request->status)->toBe('pending');
    expect($request->current_level)->toBe(3);
    expect($request->approver_id)->toBe($adminApprover->id);

    test()->actingAs($adminApprover, 'sanctum')
        ->postJson("/api/v1/validation/approval-requests/{$request->id}/approve", ['comment' => 'OK niveau 3'])
        ->assertOk();

    $request->refresh();
    expect($request->status)->toBe('approved'); // only now, after all 3
    expect($request->current_level)->toBe(3);

    // A finalized request cannot be approved again (replay protection).
    test()->actingAs($adminApprover, 'sanctum')
        ->postJson("/api/v1/validation/approval-requests/{$request->id}/approve", ['comment' => 'replay'])
        ->assertForbidden();
});

// ─────────────────────────────────────────────────────────────────────────
// Layer 9 (fake/dead) — ValidationEngine's rule-set methods are genuinely
// reachable end-to-end, not just routed-but-orphaned.
// ─────────────────────────────────────────────────────────────────────────

test('the generic validation-rule-set pipeline works end-to-end through real HTTP routes', function () {
    $company = Company::factory()->create();
    $admin = c327User($company, 'admin');

    $rule = ValidationRule::create(['name' => 'email required', 'field' => 'email', 'type' => 'required']);

    $setResponse = test()->actingAs($admin, 'sanctum')->postJson('/api/v1/validation-rule-sets', [
        'name' => 'Onboarding checks',
    ]);
    $setResponse->assertStatus(201);
    $setId = $setResponse->json('data.id');

    test()->actingAs($admin, 'sanctum')
        ->postJson("/api/v1/validation-rule-sets/{$setId}/rules", ['rule_id' => $rule->id])
        ->assertOk();

    $failing = test()->actingAs($admin, 'sanctum')
        ->postJson("/api/v1/validation-rule-sets/{$setId}/validate", ['name' => 'no email here'])
        ->assertOk();
    expect($failing->json('valid'))->toBeFalse();

    $passing = test()->actingAs($admin, 'sanctum')
        ->postJson("/api/v1/validation-rule-sets/{$setId}/validate", ['email' => 'a@b.com'])
        ->assertOk();
    expect($passing->json('valid'))->toBeTrue();
});

test('addDependency() genuinely rejects a real circular dependency through the real HTTP route', function () {
    $company = Company::factory()->create();
    $admin = c327User($company, 'admin');

    $ruleA = ValidationRule::create(['name' => 'A', 'field' => 'a', 'type' => 'required']);
    $ruleB = ValidationRule::create(['name' => 'B', 'field' => 'b', 'type' => 'required']);

    // B depends on A.
    test()->actingAs($admin, 'sanctum')
        ->postJson("/api/v1/validation-rules/{$ruleB->id}/dependencies", ['depends_on_rule_id' => $ruleA->id])
        ->assertOk();

    // A depends on B -> would close a real cycle A -> B -> A.
    $response = test()->actingAs($admin, 'sanctum')
        ->postJson("/api/v1/validation-rules/{$ruleA->id}/dependencies", ['depends_on_rule_id' => $ruleB->id]);

    $response->assertStatus(422);
    expect($ruleA->fresh()->dependencies()->count())->toBe(0);
});

// ─────────────────────────────────────────────────────────────────────────
// Layer 10 (relational/data-format) — hierarchy_id no longer silently
// dropped on rule create/update (fix #5).
// ─────────────────────────────────────────────────────────────────────────

test('POST approval-workflows/{workflow}/rules persists the hierarchy_id the Workflow Builder form actually sends', function () {
    $company = Company::factory()->create();
    $admin = c327User($company, 'admin');
    $workflow = ApprovalWorkflow::create(['name' => 'WF-hierarchy', 'module_name' => 'Inventory', 'is_active' => true, 'created_by' => $admin->id]);
    $hierarchy = ApprovalHierarchy::create(['name' => 'Inventory chain', 'module_name' => 'Inventory', 'is_active' => true]);

    $response = test()->actingAs($admin, 'sanctum')
        ->postJson("/api/v1/validation/approval-workflows/{$workflow->id}/rules", [
            'condition_type' => 'amount',
            'condition_operator' => '>',
            'condition_value' => '10000',
            'required_approvers_count' => 1,
            'approval_mode' => 'sequential',
            'hierarchy_id' => $hierarchy->id,
        ]);

    $response->assertStatus(201);
    expect($response->json('hierarchy_id'))->toBe($hierarchy->id);
    $this->assertDatabaseHas('validation_approval_rules', [
        'workflow_id' => $workflow->id,
        'hierarchy_id' => $hierarchy->id,
    ]);
});

test('PUT approval-workflows/{workflow}/rules/{rule} also persists a changed hierarchy_id', function () {
    $company = Company::factory()->create();
    $admin = c327User($company, 'admin');
    $workflow = ApprovalWorkflow::create(['name' => 'WF-hierarchy-update', 'module_name' => 'Inventory', 'is_active' => true, 'created_by' => $admin->id]);
    $hierarchyOld = ApprovalHierarchy::create(['name' => 'Old chain', 'module_name' => 'Inventory', 'is_active' => true]);
    $hierarchyNew = ApprovalHierarchy::create(['name' => 'New chain', 'module_name' => 'Inventory', 'is_active' => true]);

    $rule = \Modules\Validation\Models\ApprovalRule::create([
        'workflow_id' => $workflow->id,
        'rule_order' => 1,
        'condition_type' => 'amount',
        'condition_operator' => '>',
        'condition_value' => '0',
        'required_approvers_count' => 1,
        'approval_mode' => 'sequential',
        'hierarchy_id' => $hierarchyOld->id,
        'status' => 'active',
    ]);

    test()->actingAs($admin, 'sanctum')
        ->putJson("/api/v1/validation/approval-workflows/{$workflow->id}/rules/{$rule->id}", [
            'hierarchy_id' => $hierarchyNew->id,
        ])
        ->assertOk();

    expect($rule->fresh()->hierarchy_id)->toBe($hierarchyNew->id);
});

// ─────────────────────────────────────────────────────────────────────────
// Layer 10 — module_name now accepted when creating/updating a hierarchy
// (fix #6), previously always null through this endpoint.
// ─────────────────────────────────────────────────────────────────────────

test('POST approval-hierarchies persists module_name/escalation_role, previously always dropped', function () {
    $company = Company::factory()->create();
    $admin = c327User($company, 'admin');

    $response = test()->actingAs($admin, 'sanctum')->postJson('/api/v1/validation/approval-hierarchies', [
        'name' => 'HR escalation chain',
        'module_name' => 'HR',
        'escalation_role' => 'admin',
    ]);

    $response->assertStatus(201);
    expect($response->json('module_name'))->toBe('HR');
    expect($response->json('escalation_role'))->toBe('admin');
});

// ─────────────────────────────────────────────────────────────────────────
// Layer 11 (CORE integration) — NotifyApprovalParticipants's action_url now
// points at the real route (fix #4).
// ─────────────────────────────────────────────────────────────────────────

test('a real ApprovalRequestCreated notification carries the REAL /approval-requests/{id} url, not the dead /validation/approval-requests/{id} one', function () {
    $company = Company::factory()->create();
    $admin = c327User($company, 'admin');
    $approver = c327User($company, 'manager');

    $poA = PurchaseOrder::factory()->create(['company_id' => $company->id]);
    $workflow = ApprovalWorkflow::create(['name' => 'WF-notif-url', 'module_name' => 'Achats', 'is_active' => true, 'created_by' => $admin->id]);
    $request = app(ApprovalRequestService::class)->createApprovalRequest($poA, $workflow, $admin);
    $request->update(['approver_id' => $approver->id]);

    app(\Modules\Validation\Listeners\NotifyApprovalParticipants::class)
        ->handleRequestCreated(new \Modules\Validation\Events\ApprovalRequestCreated($request, $workflow));

    $notification = \Illuminate\Notifications\DatabaseNotification::where('notifiable_id', $approver->id)->latest()->first();

    expect($notification)->not->toBeNull();
    $data = is_array($notification->data) ? $notification->data : json_decode($notification->data, true);
    expect($data['meta']['action_url'])->toBe("/approval-requests/{$request->id}");
    expect($data['meta']['action_url'])->not->toContain('/validation/');
});

// Confirm the "real" URL is genuinely the routed one (not a coincidence),
// closing the loop this bug's fix depends on.
test('the real web route for an approval request detail page has no /validation prefix', function () {
    $routeUrl = route('validation.requests.show', ['approval_request' => 123]);
    expect(parse_url($routeUrl, PHP_URL_PATH))->toBe('/approval-requests/123');
});

// ─────────────────────────────────────────────────────────────────────────
// Layer 13 (AI) — 'Validation' is now a real, registered supported module
// with real fallback guidance for all 5 real screens, reachable through
// the actual composable's real HTTP endpoint.
// ─────────────────────────────────────────────────────────────────────────

test('AiContextualAssistantService::supportedModules() now includes Validation with its 5 real actions', function () {
    $service = app(\Modules\AI\Services\AiContextualAssistantService::class);
    $modules = $service->supportedModules();

    expect($modules)->toHaveKey('Validation');
    expect($modules['Validation'])->toEqualCanonicalizing([
        'view_approval_dashboard',
        'view_approval_request',
        'manage_workflows',
        'build_workflow',
        'manage_validation_rules',
    ]);
});

test('every Validation action has real, non-empty fallback guidance in French and English', function () {
    $service = app(\Modules\AI\Services\AiContextualAssistantService::class);

    foreach (['view_approval_dashboard', 'view_approval_request', 'manage_workflows', 'build_workflow', 'manage_validation_rules'] as $action) {
        $fr = $service->fallbackGuidance('Validation', $action, 'fr');
        $en = $service->fallbackGuidance('Validation', $action, 'en');

        expect($fr['what_to_do'])->not->toBe('');
        expect($en['what_to_do'])->not->toBe('');
        expect($fr['how_to_do'])->not->toBeEmpty();
        expect($en['how_to_do'])->not->toBeEmpty();
    }
});

test('the real /api/v1/ai/assist endpoint (used by the frontend useAiAssistant composable) returns real Validation guidance', function () {
    $company = Company::factory()->create();
    $user = c327User($company, 'employee');

    $response = test()->actingAs($user, 'sanctum')->postJson('/api/v1/ai/assist', [
        'module' => 'Validation',
        'action' => 'manage_workflows',
        'locale' => 'fr',
    ]);

    $response->assertOk();
    expect($response->json('what_to_do'))->not->toBe('');
});

// ─────────────────────────────────────────────────────────────────────────
// Layer 7 (RBAC re-confirmation) — module:Validation gate now present.
// ─────────────────────────────────────────────────────────────────────────

test('module:Validation gate denies access once the module is disabled for the tenant, and allows it once enabled', function () {
    $company = Company::factory()->create();
    $user = c327User($company, 'admin');
    $manager = app(\Modules\Core\Services\ModuleManager::class);

    // actingAsUser()-style enrolment (real tenant_modules row, real
    // ModuleManager::enable(), which also clears its own request cache).
    $manager->enable((string) $user->id, 'Validation');

    test()->actingAs($user, 'sanctum')
        ->getJson('/api/v1/validation/approval-requests')
        ->assertStatus(200);

    $manager->disable((string) $user->id, 'Validation');

    test()->actingAs($user, 'sanctum')
        ->getJson('/api/v1/validation/approval-requests')
        ->assertStatus(403);

    $manager->enable((string) $user->id, 'Validation');

    test()->actingAs($user, 'sanctum')
        ->getJson('/api/v1/validation/approval-requests')
        ->assertStatus(200);
});

// ─────────────────────────────────────────────────────────────────────────
// Layer 14 — performance: index() must not N+1 as the number of pending
// approval requests grows (mixed invoice/purchase_order approvables, mixed
// workflows/requesters/approvers).
// ─────────────────────────────────────────────────────────────────────────

test('GET approval-requests does not N+1 as the number of requests grows', function () {
    $company = Company::factory()->create();
    $admin = c327User($company, 'admin');

    $workflow = ApprovalWorkflow::create(['name' => 'WF-perf', 'module_name' => 'Achats', 'is_active' => true, 'created_by' => $admin->id]);

    for ($i = 0; $i < 20; $i++) {
        $requester = User::factory()->create(['company_id' => $company->id]);
        $approver = User::factory()->create(['company_id' => $company->id]);
        $po = PurchaseOrder::factory()->create(['company_id' => $company->id]);
        $invoice = Invoice::factory()->create();

        // Alternate approvable type so the morph eager-load has 2 real
        // distinct types to batch, not just 1.
        $approvable = $i % 2 === 0 ? $po : $invoice;

        ApprovalRequest::create([
            'workflow_id' => $workflow->id,
            'approvable_type' => $i % 2 === 0 ? 'purchase_order' : 'invoice',
            'approvable_id' => $approvable->id,
            'status' => 'pending',
            'requested_by' => $requester->id,
            'approver_id' => $approver->id,
            'company_id' => $company->id,
        ]);
    }

    DB::enableQueryLog();
    $response = test()->actingAs($admin, 'sanctum')->getJson('/api/v1/validation/approval-requests');
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    $response->assertOk();
    expect($response->json('total'))->toBe(20);
    // Paginated single query + a COUNT + a couple of eager-loads (workflow/
    // requester/approver/approvable, morph-batched by type) + auth/role-gate
    // middleware queries — never anywhere close to one query per row (would
    // be 80+ here without eager loading).
    expect($queryCount)->toBeLessThanOrEqual(20);
});
