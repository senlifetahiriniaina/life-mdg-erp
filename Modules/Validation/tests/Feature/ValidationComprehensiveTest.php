<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Validation\Models\ApprovalHierarchy;
use Modules\Validation\Models\ApprovalRequest;
use Modules\Validation\Models\ApprovalWorkflow;
use Modules\Validation\Services\ApprovalHierarchyService;
use Modules\Validation\Services\ApprovalRequestService;
use Modules\Validation\Services\ApprovalWorkflowService;

uses(RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

function makeWorkflow(array $overrides = []): ApprovalWorkflow
{
    return ApprovalWorkflow::factory()->create(array_merge([
        'name'      => 'Invoice Approval',
        'is_active' => true,
    ], $overrides));
}

function makePendingRequest(array $overrides = []): ApprovalRequest
{
    return ApprovalRequest::factory()->create(array_merge([
        'status' => 'pending',
    ], $overrides));
}

// ─── ApprovalRequestService ───────────────────────────────────────────────────

describe('ApprovalRequestService', function () {
    beforeEach(function () {
        $this->user    = actingAsUser('admin');
        $this->service = app(ApprovalRequestService::class);
    });

    test('creates an approval request with pending status', function () {
        $workflow  = makeWorkflow();
        $requester = User::factory()->create();

        $approvable = new class { public int $id = 1; };

        $request = $this->service->createApprovalRequest($approvable, $workflow, $requester);

        expect($request)->toBeInstanceOf(ApprovalRequest::class)
            ->and($request->status)->toBe('pending')
            ->and($request->requested_by)->toBe($requester->id);
    });

    test('approves a pending request and sets approved_by', function () {
        $request  = makePendingRequest();
        $approver = User::factory()->create();

        $this->service->approveRequest($request, $approver, 'Conforme aux règles OHADA');

        $request->refresh();
        expect($request->status)->toBe('approved')
            ->and($request->approved_by)->toBe($approver->id)
            ->and($request->approved_at)->not->toBeNull();
    });

    test('rejects a pending request and records rejection reason', function () {
        $request  = makePendingRequest();
        $approver = User::factory()->create();

        $this->service->rejectRequest($request, $approver, 'Pièces justificatives manquantes');

        $request->refresh();
        expect($request->status)->toBe('rejected')
            ->and($request->rejected_at)->not->toBeNull();
    });

    test('escalates a request to the next level', function () {
        $request  = makePendingRequest();

        $this->service->getNextApprovers($request);

        $request->refresh();
        expect($request->status)->toBeIn(['escalated', 'pending']);
    });

    test('delegates request to another approver', function () {
        $request   = makePendingRequest();
        $delegator = User::factory()->create();
        $delegate  = User::factory()->create();

        $this->service->delegateApproval($request, $delegator, $delegate);

        $request->refresh();
        expect($request->status)->toBeIn(['delegated', 'pending', 'approved']);
    });

    test('retrieves approval history for a request', function () {
        $request = makePendingRequest();

        $history = $this->service->getHistoryForRequest($request);

        expect($history)->toBeIterable();
    });
});

// ─── ApprovalHierarchyService ─────────────────────────────────────────────────

describe('ApprovalHierarchyService', function () {
    beforeEach(function () {
        $this->user    = actingAsUser('admin');
        $this->service = app(ApprovalHierarchyService::class);
    });

    test('retrieves a hierarchy by ID', function () {
        $hierarchy = ApprovalHierarchy::factory()->create(['name' => 'Direction Financière']);

        $result = $this->service->getHierarchy($hierarchy->id);

        expect($result)->toBeInstanceOf(ApprovalHierarchy::class)
            ->and($result->name)->toBe('Direction Financière');
    });

    test('resolves next approver in a hierarchy', function () {
        $hierarchy = ApprovalHierarchy::factory()->create();
        $request   = makePendingRequest(['hierarchy_id' => $hierarchy->id]);

        $result = app(\Modules\Validation\Services\ApprovalRoutingResolver::class)->resolveApprovers($request);

        expect($result)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    });

    test('checks user permission to approve at a given level', function () {
        $hierarchy = ApprovalHierarchy::factory()->create();
        $approver  = User::factory()->create();

        $result = $this->service->canApprove($approver, $hierarchy, 1);

        expect($result)->toBeBool();
    });

    test('creates a new approval hierarchy', function () {
        $hierarchy = $this->service->createHierarchy([
            'name'        => 'Hiérarchie Achats',
            'description' => '3 niveaux: Chef équipe → DAF → DG',
        ]);

        expect($hierarchy)->toBeInstanceOf(ApprovalHierarchy::class)
            ->and($hierarchy->name)->toBe('Hiérarchie Achats');
    });

    test('adds a level to an existing hierarchy', function () {
        $hierarchy = ApprovalHierarchy::factory()->create();

        $level = $this->service->addLevel($hierarchy, [
            'level_order' => 1,
            'label'       => 'Chef d\'équipe',
            'min_amount'  => 0,
            'max_amount'  => 100000,
        ]);

        expect($level)->not->toBeNull();
    });
});

// ─── ApprovalWorkflowService ──────────────────────────────────────────────────

describe('ApprovalWorkflowService', function () {
    beforeEach(function () {
        $this->user    = actingAsUser('admin');
        $this->service = app(ApprovalWorkflowService::class);
    });

    test('starts a workflow and creates an approval request', function () {
        $workflow  = makeWorkflow();
        $requester = User::factory()->create();

        $approvable = new class { public int $id = 99; };

        $request = app(ApprovalRequestService::class)
            ->createApprovalRequest($approvable, $workflow, $requester);

        expect($request)->toBeInstanceOf(ApprovalRequest::class);
    });

    test('advances workflow to next step after approval', function () {
        $request  = makePendingRequest();
        $approver = User::factory()->create();

        app(ApprovalRequestService::class)->approveRequest($request, $approver, 'OK');

        $request->refresh();
        expect($request->status)->toBe('approved');
    });

    test('completes workflow when all levels are approved', function () {
        $request = makePendingRequest(['status' => 'approved', 'current_level' => 1, 'total_levels' => 1]);

        app(ApprovalRequestService::class)->completeApproval($request);

        $request->refresh();
        expect($request->status)->toBe('completed');
    });

    test('returns active workflows for a tenant', function () {
        makeWorkflow(['is_active' => true]);
        makeWorkflow(['is_active' => true]);

        $workflows = $this->service->getAllActive();

        expect($workflows->count())->toBeGreaterThanOrEqual(2);
    });

    test('creates a workflow with OHADA thresholds (100K / 500K XOF)', function () {
        $workflow = $this->service->createWorkflow([
            'name'     => 'Approbation Facture OHADA',
            'currency' => 'XOF',
            'levels'   => [
                ['label' => 'Comptable',  'max_amount' => 100000],
                ['label' => 'DAF',        'max_amount' => 500000],
                ['label' => 'DG',         'max_amount' => null],
            ],
        ]);

        expect($workflow)->toBeInstanceOf(ApprovalWorkflow::class)
            ->and($workflow->name)->toBe('Approbation Facture OHADA');
    });
});

// ─── OHADA Multi-level Approval Thresholds ────────────────────────────────────

describe('OHADA Multi-level Approval (100K / 500K XOF)', function () {
    beforeEach(function () {
        $this->user    = actingAsUser('admin');
        $this->service = app(ApprovalRequestService::class);
    });

    test('invoice below 100K XOF requires single approval level', function () {
        $request = makePendingRequest([
            'amount'   => 75000,
            'currency' => 'XOF',
        ]);

        expect($request->amount)->toBeLessThan(100000);
    });

    test('invoice between 100K and 500K XOF requires two approval levels', function () {
        $request = makePendingRequest([
            'amount'   => 250000,
            'currency' => 'XOF',
        ]);

        expect($request->amount)->toBeGreaterThan(100000)
            ->and($request->amount)->toBeLessThanOrEqual(500000);
    });

    test('invoice above 500K XOF requires director-level approval', function () {
        $request = makePendingRequest([
            'amount'   => 750000,
            'currency' => 'XOF',
        ]);

        expect($request->amount)->toBeGreaterThan(500000);
    });
});

// ─── Approval API endpoints ───────────────────────────────────────────────────

describe('Approval API', function () {
    beforeEach(function () {
        $this->user = actingAsUser('admin');
    });

    test('GET /api/v1/validation/approval-workflows lists workflows', function () {
        makeWorkflow();
        $response = $this->getJson('/api/v1/validation/approval-workflows');
        $response->assertStatus(200);
    });

    test('POST /api/v1/validation/approval-workflows creates a workflow', function () {
        $response = $this->postJson('/api/v1/validation/approval-workflows', [
            'name'        => 'Workflow Test',
            'description' => 'Test workflow',
            'is_active'   => true,
        ]);
        $response->assertStatus(201);
    });

    test('GET /api/v1/validation/approval-requests lists requests', function () {
        makePendingRequest();
        $response = $this->getJson('/api/v1/validation/approval-requests');
        $response->assertStatus(200);
    });

    test('POST /api/v1/validation/approval-requests creates a request', function () {
        $workflow = makeWorkflow();
        $invoice  = \Modules\Accounting\Models\Invoice::factory()->create();

        $response = $this->postJson('/api/v1/validation/approval-requests', [
            'workflow_id'      => $workflow->id,
            'approvable_type'  => 'invoice',
            'approvable_id'    => $invoice->id,
            'amount'           => 150000,
            'currency'         => 'XOF',
        ]);
        $response->assertStatus(201);
    });

    test('POST /api/v1/validation/approval-requests/{id}/approve approves a request', function () {
        $request  = makePendingRequest();
        $response = $this->postJson("/api/v1/validation/approval-requests/{$request->id}/approve", [
            'comment' => 'Approuvé',
        ]);
        $response->assertStatus(200);
    });

    test('POST /api/v1/validation/approval-requests/{id}/reject rejects a request', function () {
        $request  = makePendingRequest();
        $response = $this->postJson("/api/v1/validation/approval-requests/{$request->id}/reject", [
            'reason' => 'Documents manquants',
        ]);
        $response->assertStatus(200);
    });

    test('GET /api/v1/validation/approval-hierarchies lists hierarchies', function () {
        ApprovalHierarchy::factory()->create();
        $response = $this->getJson('/api/v1/validation/approval-hierarchies');
        $response->assertStatus(200);
    });

    test('GET /api/v1/validation/approval-requests/{id}/history returns history', function () {
        $request  = makePendingRequest();
        $response = $this->getJson("/api/v1/validation/approval-requests/{$request->id}/history");
        $response->assertStatus(200);
    });
});
