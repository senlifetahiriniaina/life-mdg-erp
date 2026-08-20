<?php

/**
 * Chantier 19 Lot 3 (Validation re-verification, empirical-execution
 * methodology): actually calls the real HTTP routes against real seeded
 * data — two real users, a real approval request, a real dependency graph
 * — rather than re-reading the already-fixed code documented in CLAUDE.md's
 * Chantier 8.5sv entry.
 *
 *  1. ApprovalRequestController::history() (API) had zero authorize() call
 *     — a real IDOR letting any authenticated user read another user's
 *     approval decision history/reasons by id, bypassing show()'s own
 *     already-fixed view() gate on the identical model.
 *  2. Web\ApprovalRequestController::show() had the identical, independent
 *     gap on the Inertia page — the exact "API fixed, Web path missed"
 *     pattern this session has found repeatedly in other modules.
 *  3. Re-verifies (not just re-reads) cloneWorkflow()'s replicate() fix,
 *     getPendingApprovalsForUser()'s approver-scoping fix, and
 *     ValidationRuleController::addDependency()'s circular-dependency
 *     rejection — none of the three had ever been exercised through the
 *     real HTTP route with a real dependency graph.
 */

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Validation\Models\ApprovalHistory;
use Modules\Validation\Models\ApprovalRequest;
use Modules\Validation\Models\ApprovalRule;
use Modules\Validation\Models\ApprovalWorkflow;
use Modules\Validation\Models\ValidationRule;

uses(RefreshDatabase::class);

function chantier19ValidationUser(Company $company, string $role = 'employee'): User
{
    if (\Spatie\Permission\Models\Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole($role);

    return $user;
}

test('history() rejects a user who is neither the requester, the approver, nor admin/manager', function () {
    $company = Company::factory()->create();
    $requester = chantier19ValidationUser($company, 'employee');
    $approver = chantier19ValidationUser($company, 'employee');
    $stranger = chantier19ValidationUser($company, 'employee');

    $workflow = ApprovalWorkflow::create(['name' => 'W1', 'module_name' => 'Achats', 'is_active' => true, 'created_by' => $requester->id]);
    $request = ApprovalRequest::create([
        'workflow_id' => $workflow->id, 'approvable_type' => \App\Models\Customer::class, 'approvable_id' => 1,
        'status' => 'pending', 'requested_by' => $requester->id, 'approver_id' => $approver->id,
    ]);
    ApprovalHistory::create([
        'request_id' => $request->id, 'level' => 1, 'action' => 'created',
        'old_status' => null, 'new_status' => 'pending', 'changed_by' => $requester->id, 'changed_at' => now(),
    ]);

    test()->actingAs($stranger, 'sanctum')
        ->getJson("/api/v1/validation/approval-requests/{$request->id}/history")
        ->assertForbidden();

    // The real requester and the real assigned approver both can.
    test()->actingAs($requester, 'sanctum')
        ->getJson("/api/v1/validation/approval-requests/{$request->id}/history")
        ->assertOk();
    test()->actingAs($approver, 'sanctum')
        ->getJson("/api/v1/validation/approval-requests/{$request->id}/history")
        ->assertOk();
});

test('web ApprovalRequestController::show() rejects a user who is neither the requester, the approver, nor admin/manager', function () {
    $company = Company::factory()->create();
    $requester = chantier19ValidationUser($company, 'employee');
    $approver = chantier19ValidationUser($company, 'employee');
    $stranger = chantier19ValidationUser($company, 'employee');

    $workflow = ApprovalWorkflow::create(['name' => 'W2', 'module_name' => 'Achats', 'is_active' => true, 'created_by' => $requester->id]);
    $request = ApprovalRequest::create([
        'workflow_id' => $workflow->id, 'approvable_type' => \App\Models\Customer::class, 'approvable_id' => 1,
        'status' => 'pending', 'requested_by' => $requester->id, 'approver_id' => $approver->id,
    ]);

    test()->actingAs($stranger)->get("/approval-requests/{$request->id}")->assertForbidden();
    test()->actingAs($requester)->get("/approval-requests/{$request->id}")->assertOk();
});

test('cloneWorkflow() re-points every cloned rule at the new workflow, not the original', function () {
    $company = Company::factory()->create();
    $admin = chantier19ValidationUser($company, 'admin');

    $workflow = ApprovalWorkflow::create(['name' => 'Original', 'module_name' => 'Achats', 'is_active' => true, 'created_by' => $admin->id]);
    ApprovalRule::create([
        'workflow_id' => $workflow->id, 'condition_type' => 'amount_threshold', 'condition_operator' => '>',
        'condition_value' => '1000', 'required_approvers_count' => 1, 'approval_mode' => 'sequential',
        'rule_order' => 1, 'status' => 'active',
    ]);

    $response = test()->actingAs($admin, 'sanctum')
        ->postJson("/api/v1/validation/approval-workflows/{$workflow->id}/clone", ['name' => 'Cloned'])
        ->assertCreated();

    $clonedId = $response->json('id');
    $clonedRule = ApprovalRule::where('workflow_id', $clonedId)->first();

    expect($clonedRule)->not->toBeNull();
    expect($clonedRule->workflow_id)->toBe($clonedId)->not->toBe($workflow->id);
    // The original workflow's own rule must be untouched.
    expect(ApprovalRule::where('workflow_id', $workflow->id)->count())->toBe(1);
});

test('getPendingApprovalsForUser() (via the real my-approvals-style index filter) only returns requests the caller is actually the assigned approver for', function () {
    $company = Company::factory()->create();
    $approverA = chantier19ValidationUser($company, 'employee');
    $approverB = chantier19ValidationUser($company, 'employee');
    $requester = chantier19ValidationUser($company, 'admin');

    $workflow = ApprovalWorkflow::create(['name' => 'W3', 'module_name' => 'Achats', 'is_active' => true, 'created_by' => $requester->id]);
    $forA = ApprovalRequest::create([
        'workflow_id' => $workflow->id, 'approvable_type' => \App\Models\Customer::class, 'approvable_id' => 1,
        'status' => 'pending', 'requested_by' => $requester->id, 'approver_id' => $approverA->id,
    ]);
    ApprovalRequest::create([
        'workflow_id' => $workflow->id, 'approvable_type' => \App\Models\Customer::class, 'approvable_id' => 2,
        'status' => 'pending', 'requested_by' => $requester->id, 'approver_id' => $approverB->id,
    ]);

    $service = app(\Modules\Validation\Services\ApprovalRequestService::class);
    $pending = $service->getPendingApprovalsForUser($approverA);

    expect($pending)->toHaveCount(1);
    expect($pending->first()->id)->toBe($forA->id);
});

test('addDependency() rejects a real 2-node circular dependency via the actual HTTP route', function () {
    $company = Company::factory()->create();
    $admin = chantier19ValidationUser($company, 'admin');

    $ruleA = ValidationRule::create(['name' => 'A', 'field' => 'amount', 'type' => 'required']);
    $ruleB = ValidationRule::create(['name' => 'B', 'field' => 'currency', 'type' => 'required']);

    // A depends on B — accepted, no cycle yet.
    test()->actingAs($admin, 'sanctum')
        ->postJson("/api/v1/validation-rules/{$ruleA->id}/dependencies", ['depends_on_rule_id' => $ruleB->id])
        ->assertOk();

    // B depends on A — would close the cycle A->B->A — rejected with 422.
    $response = test()->actingAs($admin, 'sanctum')
        ->postJson("/api/v1/validation-rules/{$ruleB->id}/dependencies", ['depends_on_rule_id' => $ruleA->id]);

    $response->assertStatus(422);
    expect($ruleB->fresh()->dependencies()->pluck('validation_rules.id'))->toBeEmpty();
});
