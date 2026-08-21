<?php

/**
 * Chantier 31 — "reconfirm the validation chains are in place and
 * functioning for all modules" re-audit of Modules\Validation itself (the
 * core, generic, reusable approval-chain engine). Empirical-execution
 * methodology per this session's own established rule: every claim below is
 * backed by a real HTTP request against real seeded data, not a re-read of
 * the code already documented as fixed in CLAUDE.md's Chantier 8.5sv/
 * Chantier 19 Lot 3 entries.
 *
 * The route → controller → service → model trace for every real route in
 * routes/api.php + routes/validation-rules.php was re-walked before writing
 * anything here — no dead method calls and no route-parameter-name
 * mismatches were found (the exact class of bug documented as fixed
 * elsewhere in this session's "Chantier 19 Lot 3" entry, and already
 * corrected in this module's own routes/api.php per its inline comments —
 * re-verified still correct via the "operates on the real bound model, not
 * a blank unsaved one" tests below).
 *
 * The one REAL, previously-undocumented bug this re-audit found and fixed:
 * validation_approval_requests had ZERO tenant/company scoping of any kind,
 * despite existing solely to gate access to per-tenant business records
 * (invoices, purchase orders, ...). ApprovalRequestPolicy::view()/approve()/
 * reject()/delegate() granted a blanket bypass to `hasAnyRole(['admin',
 * 'manager'])` regardless of which company the request's requester/approver
 * actually belonged to — and ApprovalRequestController::index() applied no
 * scoping at all. Confirmed empirically (before any fix) via a real
 * cross-company HTTP request: a Company B manager could list Company A's
 * pending approval requests, view one by id, and read its full history.
 * validation_approval_hierarchies had an unenforced company_id column with
 * the identical shape of bug (real design intent, per
 * ApprovalHierarchyService::getHierarchyByCompany(), never checked anywhere
 * in the controller) — a Company B admin could list/view/UPDATE Company A's
 * hierarchy by id.
 */

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Achats\Models\PurchaseOrder;
use Modules\Validation\Models\ApprovalHierarchy;
use Modules\Validation\Models\ApprovalRequest;
use Modules\Validation\Models\ApprovalRule;
use Modules\Validation\Models\ApprovalWorkflow;
use Modules\Validation\Models\ValidationRule;
use Modules\Validation\Services\ApprovalRequestService;

uses(RefreshDatabase::class);

function c31User(?Company $company, string $role): User
{
    if (\Spatie\Permission\Models\Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }
    $user = User::factory()->create(['company_id' => $company?->id]);
    $user->assignRole($role);

    return $user;
}

// ── Cross-tenant leak: ApprovalRequest (headline finding) ──────────────────

test('ApprovalRequestService::createApprovalRequest() populates company_id from the real requester, never left null for a normal user', function () {
    $company = Company::factory()->create();
    $requester = c31User($company, 'employee');

    $po = PurchaseOrder::factory()->create(['company_id' => $company->id]);
    $workflow = ApprovalWorkflow::create(['name' => 'WF-populate', 'module_name' => 'Achats', 'is_active' => true, 'created_by' => $requester->id]);

    $request = app(ApprovalRequestService::class)->createApprovalRequest($po, $workflow, $requester);

    expect($request->company_id)->toBe($company->id);
});

test('GET approval-requests no longer leaks another company\'s pending requests to a manager', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    $adminA = c31User($companyA, 'admin');
    $managerB = c31User($companyB, 'manager');

    $poA = PurchaseOrder::factory()->create(['company_id' => $companyA->id]);
    $workflow = ApprovalWorkflow::create(['name' => 'WF-A', 'module_name' => 'Achats', 'is_active' => true, 'created_by' => $adminA->id]);
    $requestA = app(ApprovalRequestService::class)->createApprovalRequest($poA, $workflow, $adminA);

    $response = test()->actingAs($managerB, 'sanctum')->getJson('/api/v1/validation/approval-requests');
    $response->assertOk();

    $visibleIds = collect($response->json('data'))->pluck('id')->all();
    expect($visibleIds)->not->toContain($requestA->id);
});

test('GET approval-requests still returns the SAME company\'s requests to its own manager', function () {
    $companyA = Company::factory()->create();
    $adminA = c31User($companyA, 'admin');
    $managerA = c31User($companyA, 'manager');

    $poA = PurchaseOrder::factory()->create(['company_id' => $companyA->id]);
    $workflow = ApprovalWorkflow::create(['name' => 'WF-A2', 'module_name' => 'Achats', 'is_active' => true, 'created_by' => $adminA->id]);
    $requestA = app(ApprovalRequestService::class)->createApprovalRequest($poA, $workflow, $adminA);

    $response = test()->actingAs($managerA, 'sanctum')->getJson('/api/v1/validation/approval-requests');
    $response->assertOk();

    $visibleIds = collect($response->json('data'))->pluck('id')->all();
    expect($visibleIds)->toContain($requestA->id);
});

test('GET approval-requests/{id} (show) returns 403 for a different company\'s admin, 200 for the same company\'s admin', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    $adminA = c31User($companyA, 'admin');
    $adminA2 = c31User($companyA, 'admin');
    $adminB = c31User($companyB, 'admin');

    $poA = PurchaseOrder::factory()->create(['company_id' => $companyA->id]);
    $workflow = ApprovalWorkflow::create(['name' => 'WF-show', 'module_name' => 'Achats', 'is_active' => true, 'created_by' => $adminA->id]);
    $requestA = app(ApprovalRequestService::class)->createApprovalRequest($poA, $workflow, $adminA);

    test()->actingAs($adminB, 'sanctum')
        ->getJson("/api/v1/validation/approval-requests/{$requestA->id}")
        ->assertForbidden();

    test()->actingAs($adminA2, 'sanctum')
        ->getJson("/api/v1/validation/approval-requests/{$requestA->id}")
        ->assertOk();
});

test('history() denies a different company\'s admin, matching the Chantier 8.5sv per-record fix now extended to cross-company', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    $adminA = c31User($companyA, 'admin');
    $adminB = c31User($companyB, 'admin');

    $poA = PurchaseOrder::factory()->create(['company_id' => $companyA->id]);
    $workflow = ApprovalWorkflow::create(['name' => 'WF-history', 'module_name' => 'Achats', 'is_active' => true, 'created_by' => $adminA->id]);
    $requestA = app(ApprovalRequestService::class)->createApprovalRequest($poA, $workflow, $adminA);

    test()->actingAs($adminB, 'sanctum')
        ->getJson("/api/v1/validation/approval-requests/{$requestA->id}/history")
        ->assertForbidden();
});

test('approve()/reject() are denied to a different company\'s admin even though the route itself is not role-gated', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    $adminA = c31User($companyA, 'admin');
    $adminB = c31User($companyB, 'admin');

    $poA = PurchaseOrder::factory()->create(['company_id' => $companyA->id]);
    $workflow = ApprovalWorkflow::create(['name' => 'WF-approve', 'module_name' => 'Achats', 'is_active' => true, 'created_by' => $adminA->id]);
    $requestA = app(ApprovalRequestService::class)->createApprovalRequest($poA, $workflow, $adminA);

    test()->actingAs($adminB, 'sanctum')
        ->postJson("/api/v1/validation/approval-requests/{$requestA->id}/approve", ['comment' => 'hijack'])
        ->assertForbidden();

    test()->actingAs($adminB, 'sanctum')
        ->postJson("/api/v1/validation/approval-requests/{$requestA->id}/reject", ['reason' => 'hijack'])
        ->assertForbidden();

    // The requester's own company admin still can.
    $adminA2 = c31User($companyA, 'admin');
    test()->actingAs($adminA2, 'sanctum')
        ->postJson("/api/v1/validation/approval-requests/{$requestA->id}/approve", ['comment' => 'ok'])
        ->assertOk();
});

test('the assigned approver can always view/approve their own request regardless of company_id bookkeeping', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    $requester = c31User($companyA, 'employee');
    // Deliberately a different company than the requester — this app allows
    // a cross-company approver assignment at the data level (no FK/company
    // constraint ties approver_id to the requester's company); the fix must
    // not break the one legitimate bypass this policy has always granted:
    // the person actually assigned to decide.
    $approver = c31User($companyB, 'manager');

    $poA = PurchaseOrder::factory()->create(['company_id' => $companyA->id]);
    $workflow = ApprovalWorkflow::create(['name' => 'WF-assigned', 'module_name' => 'Achats', 'is_active' => true, 'created_by' => $requester->id]);
    $request = ApprovalRequest::create([
        'workflow_id' => $workflow->id,
        'approvable_type' => 'purchase_order',
        'approvable_id' => $poA->id,
        'status' => 'pending',
        'requested_by' => $requester->id,
        'company_id' => $companyA->id,
        'approver_id' => $approver->id,
    ]);

    test()->actingAs($approver, 'sanctum')
        ->getJson("/api/v1/validation/approval-requests/{$request->id}")
        ->assertOk();

    test()->actingAs($approver, 'sanctum')
        ->postJson("/api/v1/validation/approval-requests/{$request->id}/approve", ['comment' => 'fine'])
        ->assertOk();
});

test('super-admin bypasses the company scoping on both index() and per-record checks', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    $adminA = c31User($companyA, 'admin');
    $superAdmin = c31User($companyB, 'super-admin');

    $poA = PurchaseOrder::factory()->create(['company_id' => $companyA->id]);
    $workflow = ApprovalWorkflow::create(['name' => 'WF-super', 'module_name' => 'Achats', 'is_active' => true, 'created_by' => $adminA->id]);
    $requestA = app(ApprovalRequestService::class)->createApprovalRequest($poA, $workflow, $adminA);

    $indexResponse = test()->actingAs($superAdmin, 'sanctum')->getJson('/api/v1/validation/approval-requests');
    $indexResponse->assertOk();
    expect(collect($indexResponse->json('data'))->pluck('id')->all())->toContain($requestA->id);

    test()->actingAs($superAdmin, 'sanctum')
        ->getJson("/api/v1/validation/approval-requests/{$requestA->id}")
        ->assertOk();
});

// ── Cross-tenant leak: ApprovalHierarchy ────────────────────────────────────

test('a company-scoped hierarchy is invisible to a different company\'s admin (index + show), visible to its own', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    $adminA = c31User($companyA, 'admin');
    $adminB = c31User($companyB, 'admin');

    $hierarchy = ApprovalHierarchy::create([
        'name' => 'Company A only', 'company_id' => $companyA->id, 'module_name' => 'Achats', 'is_active' => true,
    ]);

    $indexAsB = test()->actingAs($adminB, 'sanctum')->getJson('/api/v1/validation/approval-hierarchies');
    $indexAsB->assertOk();
    expect(collect($indexAsB->json('data'))->pluck('id')->all())->not->toContain($hierarchy->id);

    test()->actingAs($adminB, 'sanctum')
        ->getJson("/api/v1/validation/approval-hierarchies/{$hierarchy->id}")
        ->assertNotFound();

    $indexAsA = test()->actingAs($adminA, 'sanctum')->getJson('/api/v1/validation/approval-hierarchies');
    expect(collect($indexAsA->json('data'))->pluck('id')->all())->toContain($hierarchy->id);

    test()->actingAs($adminA, 'sanctum')
        ->getJson("/api/v1/validation/approval-hierarchies/{$hierarchy->id}")
        ->assertOk();
});

test('a different company\'s admin can no longer UPDATE or DELETE another company\'s hierarchy by id', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $adminB = c31User($companyB, 'admin');

    $hierarchy = ApprovalHierarchy::create([
        'name' => 'Not yours', 'company_id' => $companyA->id, 'module_name' => 'Achats', 'is_active' => true,
    ]);

    test()->actingAs($adminB, 'sanctum')
        ->putJson("/api/v1/validation/approval-hierarchies/{$hierarchy->id}", ['name' => 'Hijacked'])
        ->assertNotFound();

    test()->actingAs($adminB, 'sanctum')
        ->deleteJson("/api/v1/validation/approval-hierarchies/{$hierarchy->id}")
        ->assertNotFound();

    expect($hierarchy->fresh()->name)->toBe('Not yours');
    expect(ApprovalHierarchy::find($hierarchy->id))->not->toBeNull();
});

test('a global (NULL company_id) hierarchy — the shared-config convention used by Achats\' seeded defaults — stays visible/editable to any company\'s admin', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $adminB = c31User($companyB, 'admin');

    $global = ApprovalHierarchy::create([
        'name' => 'Standard PO Approval - Tier 1', 'company_id' => null, 'module_name' => 'Achats', 'is_active' => true,
    ]);

    test()->actingAs($adminB, 'sanctum')
        ->getJson("/api/v1/validation/approval-hierarchies/{$global->id}")
        ->assertOk();

    test()->actingAs($adminB, 'sanctum')
        ->putJson("/api/v1/validation/approval-hierarchies/{$global->id}", ['name' => 'Renamed by B, still global'])
        ->assertOk();
});

test('POST approval-hierarchies never trusts a client-supplied company_id — it is always the creator\'s own', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $adminA = c31User($companyA, 'admin');

    $response = test()->actingAs($adminA, 'sanctum')
        ->postJson('/api/v1/validation/approval-hierarchies', [
            'name' => 'Spoof attempt',
            // Even if a caller supplies someone else's company_id, it must
            // never be honored — the route doesn't even validate this key
            // any more, so it's silently ignored, not partially trusted.
            'company_id' => $companyB->id,
        ])
        ->assertCreated();

    $created = ApprovalHierarchy::where('name', 'Spoof attempt')->firstOrFail();
    expect($created->company_id)->toBe($companyA->id)->not->toBe($companyB->id);
});

// ── Route → controller → model binding sanity (the class of bug already
//    fixed per this module's own routes/api.php comments — re-confirmed
//    empirically that every route genuinely operates on the REAL bound
//    model, not a blank unsaved one, rather than trusting the comments) ──

test('ApprovalWorkflowController::show()/update() operate on the real workflow, not a blank unsaved one', function () {
    $company = Company::factory()->create();
    $admin = c31User($company, 'admin');

    $workflow = ApprovalWorkflow::create(['name' => 'Real Workflow', 'module_name' => 'Achats', 'is_active' => true, 'created_by' => $admin->id]);

    $show = test()->actingAs($admin, 'sanctum')->getJson("/api/v1/validation/approval-workflows/{$workflow->id}");
    $show->assertOk();
    expect($show->json('name'))->toBe('Real Workflow');

    $update = test()->actingAs($admin, 'sanctum')
        ->putJson("/api/v1/validation/approval-workflows/{$workflow->id}", ['name' => 'Renamed Workflow'])
        ->assertOk();
    expect($update->json('name'))->toBe('Renamed Workflow');
    // A route-model-binding bug (blank unsaved model) would have INSERTed a
    // second row via update()'s underlying save() instead of updating the
    // real one — assert there is still exactly one workflow row.
    expect(ApprovalWorkflow::count())->toBe(1);
});

test('ApprovalHierarchyController addLevel/addLevelApprovers bind BOTH route segments to the real records', function () {
    $company = Company::factory()->create();
    $admin = c31User($company, 'admin');
    $approver = c31User($company, 'employee');

    $hierarchy = ApprovalHierarchy::create(['name' => 'H-binding', 'company_id' => $company->id, 'module_name' => 'Achats', 'is_active' => true]);

    $levelResp = test()->actingAs($admin, 'sanctum')
        ->postJson("/api/v1/validation/approval-hierarchies/{$hierarchy->id}/levels", [
            'title' => 'Level 1', 'approver_count' => 1, 'delegation_allowed' => true,
        ])->assertCreated();

    $levelId = $levelResp->json('id');
    expect(\Modules\Validation\Models\HierarchyLevel::where('id', $levelId)->where('hierarchy_id', $hierarchy->id)->exists())->toBeTrue();

    test()->actingAs($admin, 'sanctum')
        ->postJson("/api/v1/validation/approval-hierarchies/{$hierarchy->id}/levels/{$levelId}/approvers", [
            'user_ids' => [$approver->id],
        ])->assertOk();

    expect(\Modules\Validation\Models\LevelApprover::where('hierarchy_level_id', $levelId)->where('user_id', $approver->id)->exists())->toBeTrue();
});

// ── RBAC: unauthenticated 401, unauthorized-role 403 on mutating endpoints ──

test('unauthenticated requests are rejected with 401, not silently allowed', function () {
    test()->getJson('/api/v1/validation/approval-workflows')->assertUnauthorized();
    test()->getJson('/api/v1/validation-rules')->assertUnauthorized();
    test()->postJson('/api/v1/validation-rules', ['name' => 'x', 'field' => 'x', 'type' => 'required'])->assertUnauthorized();
    test()->getJson('/api/v1/validation/approval-requests')->assertUnauthorized();
});

test('a role with no validation-management permission gets 403 on every mutating validation-rule/hierarchy endpoint', function () {
    $company = Company::factory()->create();
    $salesRep = c31User($company, 'sales-rep');

    test()->actingAs($salesRep, 'sanctum')
        ->postJson('/api/v1/validation-rules', ['name' => 'x', 'field' => 'amount', 'type' => 'required'])
        ->assertForbidden();

    $rule = ValidationRule::create(['name' => 'r', 'field' => 'amount', 'type' => 'required']);
    test()->actingAs($salesRep, 'sanctum')
        ->putJson("/api/v1/validation-rules/{$rule->id}", ['name' => 'renamed'])
        ->assertForbidden();
    test()->actingAs($salesRep, 'sanctum')
        ->deleteJson("/api/v1/validation-rules/{$rule->id}")
        ->assertForbidden();

    test()->actingAs($salesRep, 'sanctum')
        ->postJson('/api/v1/validation/approval-hierarchies', ['name' => 'nope'])
        ->assertForbidden();

    $hierarchy = ApprovalHierarchy::create(['name' => 'H-rbac', 'module_name' => 'Achats', 'is_active' => true]);
    test()->actingAs($salesRep, 'sanctum')
        ->putJson("/api/v1/validation/approval-hierarchies/{$hierarchy->id}", ['name' => 'nope'])
        ->assertForbidden();
    test()->actingAs($salesRep, 'sanctum')
        ->deleteJson("/api/v1/validation/approval-hierarchies/{$hierarchy->id}")
        ->assertForbidden();

    $wf = ApprovalWorkflow::create(['name' => 'H-rbac-wf', 'module_name' => 'Achats', 'is_active' => true]);
    test()->actingAs($salesRep, 'sanctum')
        ->postJson("/api/v1/validation/approval-workflows/{$wf->id}/rules", [
            'condition_type' => 'amount_threshold', 'condition_operator' => '>', 'condition_value' => '1',
            'required_approvers_count' => 1, 'approval_mode' => 'sequential',
        ])->assertForbidden();
});

// ── Data-format sanity: the actual response shape a frontend consumes ──────

test('the approval-requests index response shape includes company_id and the requester\'s own company sees a real, non-empty paginator', function () {
    $company = Company::factory()->create();
    $admin = c31User($company, 'admin');

    $po = PurchaseOrder::factory()->create(['company_id' => $company->id]);
    $workflow = ApprovalWorkflow::create(['name' => 'WF-shape', 'module_name' => 'Achats', 'is_active' => true, 'created_by' => $admin->id]);
    app(ApprovalRequestService::class)->createApprovalRequest($po, $workflow, $admin);

    $response = test()->actingAs($admin, 'sanctum')->getJson('/api/v1/validation/approval-requests')->assertOk();

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0'))->toHaveKey('company_id');
    expect($response->json('data.0.company_id'))->toBe($company->id);
});
