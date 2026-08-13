<?php

namespace Modules\Validation\Tests\Feature;

use App\Models\User;
use Modules\Validation\Models\ApprovalRequest;
use Modules\Validation\Models\ApprovalWorkflow;
use Modules\Validation\Services\ApprovalRequestService;
use Tests\TestCase;

class ApprovalRequestTest extends TestCase
{
    protected ApprovalRequestService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ApprovalRequestService::class);
    }

    public function test_can_create_approval_request()
    {
        $workflow = ApprovalWorkflow::factory()->create();
        $requester = User::factory()->create();

        // Mock approvable object
        $approvable = new class
        {
            public $id = 1;
        };

        $request = $this->service->createApprovalRequest($approvable, $workflow, $requester);

        $this->assertInstanceOf(ApprovalRequest::class, $request);
        $this->assertEquals('pending', $request->status);
        $this->assertEquals($requester->id, $request->requested_by);
    }

    public function test_can_approve_request()
    {
        $request = ApprovalRequest::factory()->create(['status' => 'pending']);
        $approver = User::factory()->create();

        $this->service->approveRequest($request, $approver, 'Looks good');

        $request->refresh();
        $this->assertEquals('approved', $request->status);
        $this->assertEquals($approver->id, $request->approved_by);
        $this->assertNotNull($request->approved_at);
    }

    public function test_can_reject_request()
    {
        $request = ApprovalRequest::factory()->create(['status' => 'pending']);
        $approver = User::factory()->create();

        $this->service->rejectRequest($request, $approver, 'Missing documentation');

        $request->refresh();
        $this->assertEquals('rejected', $request->status);
        $this->assertNotNull($request->rejected_at);
    }

    public function test_approval_creates_history_entry()
    {
        $request = ApprovalRequest::factory()->create(['status' => 'pending']);
        $approver = User::factory()->create();

        $this->service->approveRequest($request, $approver);

        $history = $request->history()->first();
        $this->assertNotNull($history);
        $this->assertEquals('approved', $history->action);
        $this->assertEquals('pending', $history->old_status);
        $this->assertEquals('approved', $history->new_status);
    }

    public function test_approval_creates_action_entry()
    {
        $request = ApprovalRequest::factory()->create(['status' => 'pending']);
        $approver = User::factory()->create();

        $this->service->approveRequest($request, $approver, 'Test comment');

        $action = $request->actions()->first();
        $this->assertNotNull($action);
        $this->assertEquals('approved', $action->action);
        $this->assertEquals('Test comment', $action->comment);
    }

    public function test_pending_approvals_scope()
    {
        ApprovalRequest::factory()->count(2)->create(['status' => 'pending']);
        ApprovalRequest::factory()->count(3)->create(['status' => 'approved']);

        $pending = ApprovalRequest::pending()->get();

        $this->assertEquals(2, $pending->count());
    }

    public function test_check_is_approval_overdue()
    {
        $request = ApprovalRequest::factory()->create([
            'status' => 'pending',
            'created_at' => now()->subDays(20),
        ]);

        $this->assertTrue($this->service->isApprovalOverdue($request));
    }

    public function test_get_pending_approvals_for_user()
    {
        $user = User::factory()->create();
        ApprovalRequest::factory()->count(3)->create(['status' => 'pending']);

        $pending = $this->service->getPendingApprovalsForUser($user);

        $this->assertIsIterable($pending);
    }
}
