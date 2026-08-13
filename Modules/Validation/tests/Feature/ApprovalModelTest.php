<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Validation\Models\ApprovalAction;
use Modules\Validation\Models\ApprovalHistory;
use Modules\Validation\Models\ApprovalRequest;
use Modules\Validation\Models\ApprovalRule;
use Modules\Validation\Models\ApprovalWorkflow;
use Modules\Validation\Services\ApprovalRequestService;

uses(RefreshDatabase::class);

// ──────────────────────────────────────────────────────────────────
// ApprovalWorkflow MODEL TESTS
// ──────────────────────────────────────────────────────────────────

it('creates an approval workflow with active flag', function () {
    $user = User::factory()->create();

    $workflow = ApprovalWorkflow::factory()->create([
        'name'       => 'Purchase Order Approval',
        'module_name' => 'Accounting',
        'is_active'  => true,
        'created_by' => $user->id,
    ]);

    expect($workflow->isActive())->toBeTrue()
        ->and($workflow->name)->toBe('Purchase Order Approval');
});

it('inactive workflow returns false from isActive', function () {
    $user     = User::factory()->create();
    $workflow = ApprovalWorkflow::factory()->create([
        'is_active'  => false,
        'created_by' => $user->id,
    ]);

    expect($workflow->isActive())->toBeFalse();
});

it('workflow has many rules relationship', function () {
    $user     = User::factory()->create();
    $workflow = ApprovalWorkflow::factory()->create(['created_by' => $user->id]);

    ApprovalRule::factory()->count(3)->create(['workflow_id' => $workflow->id]);

    expect($workflow->rules)->toHaveCount(3);
});

it('workflow has many approval requests relationship', function () {
    $user     = User::factory()->create();
    $workflow = ApprovalWorkflow::factory()->create(['created_by' => $user->id]);

    ApprovalRequest::factory()->count(2)->create(['workflow_id' => $workflow->id]);

    expect($workflow->requests)->toHaveCount(2);
});

it('getRulesByOrder returns rules sorted by rule_order', function () {
    $user     = User::factory()->create();
    $workflow = ApprovalWorkflow::factory()->create(['created_by' => $user->id]);

    ApprovalRule::factory()->create(['workflow_id' => $workflow->id, 'rule_order' => 3]);
    ApprovalRule::factory()->create(['workflow_id' => $workflow->id, 'rule_order' => 1]);
    ApprovalRule::factory()->create(['workflow_id' => $workflow->id, 'rule_order' => 2]);

    $rules = $workflow->getRulesByOrder();

    expect($rules->first()->rule_order)->toBe(1)
        ->and($rules->last()->rule_order)->toBe(3);
});

// ──────────────────────────────────────────────────────────────────
// ApprovalRequest MODEL TESTS
// ──────────────────────────────────────────────────────────────────

it('approval request defaults to pending status', function () {
    $workflow = ApprovalWorkflow::factory()->create();
    $requester = User::factory()->create();

    $request = ApprovalRequest::factory()->create([
        'workflow_id'  => $workflow->id,
        'status'       => 'pending',
        'requested_by' => $requester->id,
    ]);

    expect($request->isPending())->toBeTrue()
        ->and($request->isApproved())->toBeFalse()
        ->and($request->isRejected())->toBeFalse();
});

it('scopePending returns only pending requests', function () {
    ApprovalRequest::factory()->count(3)->create(['status' => 'pending']);
    ApprovalRequest::factory()->count(2)->create(['status' => 'approved']);
    ApprovalRequest::factory()->count(1)->create(['status' => 'rejected']);

    $pending = ApprovalRequest::pending()->get();

    expect($pending)->toHaveCount(3);
});

it('scopeApproved returns only approved requests', function () {
    ApprovalRequest::factory()->count(2)->create(['status' => 'approved']);
    ApprovalRequest::factory()->count(3)->create(['status' => 'pending']);

    $approved = ApprovalRequest::approved()->get();

    expect($approved)->toHaveCount(2);
});

it('scopeRejected returns only rejected requests', function () {
    ApprovalRequest::factory()->count(1)->create(['status' => 'rejected']);
    ApprovalRequest::factory()->count(2)->create(['status' => 'pending']);

    $rejected = ApprovalRequest::rejected()->get();

    expect($rejected)->toHaveCount(1);
});

it('approve method changes status to approved and records action and history', function () {
    $request  = ApprovalRequest::factory()->create(['status' => 'pending']);
    $approver = User::factory()->create();

    $request->approve($approver, 'Approved by manager');

    $request->refresh();
    expect($request->isApproved())->toBeTrue()
        ->and($request->approved_by)->toBe($approver->id)
        ->and($request->approved_at)->not->toBeNull();

    expect($request->actions()->where('action', 'approved')->count())->toBe(1)
        ->and($request->history()->where('action', 'approved')->count())->toBe(1);
});

it('reject method changes status to rejected and records action and history', function () {
    $request  = ApprovalRequest::factory()->create(['status' => 'pending']);
    $approver = User::factory()->create();

    $request->reject($approver, 'Missing required documents');

    $request->refresh();
    expect($request->isRejected())->toBeTrue()
        ->and($request->rejected_at)->not->toBeNull();

    expect($request->actions()->where('action', 'rejected')->count())->toBe(1)
        ->and($request->history()->where('action', 'rejected')->count())->toBe(1);
});

it('markCompleted transitions approved request to completed', function () {
    $request = ApprovalRequest::factory()->create(['status' => 'approved']);

    $request->markCompleted();

    expect($request->fresh()->status)->toBe('completed');
});

it('markCompleted does nothing on pending request', function () {
    $request = ApprovalRequest::factory()->create(['status' => 'pending']);

    $request->markCompleted();

    expect($request->fresh()->status)->toBe('pending');
});

// ──────────────────────────────────────────────────────────────────
// ApprovalRequestService TESTS
// ──────────────────────────────────────────────────────────────────

it('service creates approval request with pending status', function () {
    $workflow  = ApprovalWorkflow::factory()->create();
    $requester = User::factory()->create();

    $approvable = new class {
        public int $id = 42;
    };

    $service = app(ApprovalRequestService::class);
    $request = $service->createApprovalRequest($approvable, $workflow, $requester);

    expect($request->status)->toBe('pending')
        ->and($request->requested_by)->toBe($requester->id)
        ->and($request->workflow_id)->toBe($workflow->id);
});

it('service approveRequest delegates to model approve', function () {
    $request  = ApprovalRequest::factory()->create(['status' => 'pending']);
    $approver = User::factory()->create();

    $service = app(ApprovalRequestService::class);
    $service->approveRequest($request, $approver, 'All good');

    expect($request->fresh()->isApproved())->toBeTrue();
});

it('service rejectRequest delegates to model reject', function () {
    $request  = ApprovalRequest::factory()->create(['status' => 'pending']);
    $approver = User::factory()->create();

    $service = app(ApprovalRequestService::class);
    $service->rejectRequest($request, $approver, 'Budget exceeded');

    expect($request->fresh()->isRejected())->toBeTrue();
});

it('service completeApproval marks approved request as completed', function () {
    $request = ApprovalRequest::factory()->create(['status' => 'approved']);

    $service = app(ApprovalRequestService::class);
    $service->completeApproval($request);

    expect($request->fresh()->status)->toBe('completed');
});

it('service isApprovalOverdue returns false for recent pending request', function () {
    $request = ApprovalRequest::factory()->create([
        'status'     => 'pending',
        'created_at' => now()->subDays(1),
    ]);

    $service = app(ApprovalRequestService::class);

    expect($service->isApprovalOverdue($request))->toBeFalse();
});

it('service cancelApprovalRequest changes status to cancelled', function () {
    $request = ApprovalRequest::factory()->create(['status' => 'pending']);

    $service = app(ApprovalRequestService::class);
    $service->cancelApprovalRequest($request, 'No longer needed');

    expect($request->fresh()->status)->toBe('cancelled');
});

// ──────────────────────────────────────────────────────────────────
// ApprovalRule MODEL TESTS
// ──────────────────────────────────────────────────────────────────

it('creates approval rule with required approvers count', function () {
    $workflow = ApprovalWorkflow::factory()->create();

    $rule = ApprovalRule::factory()->create([
        'workflow_id'              => $workflow->id,
        'required_approvers_count' => 2,
        'approval_mode'            => 'any',
        'rule_order'               => 1,
    ]);

    expect($rule->required_approvers_count)->toBe(2)
        ->and($rule->approval_mode)->toBe('any');
});
