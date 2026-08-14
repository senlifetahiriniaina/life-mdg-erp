<?php

use Modules\HR\Models\Employee;
use Modules\HR\Models\LeaveRequest;
use Modules\HR\Models\LeaveType;

describe('Leave Request API', function () {
    beforeEach(function () {
        $this->user = actingAsUser('admin');
    });

    test('can request leave', function () {
        $employee = Employee::factory()->create();
        $leaveType = LeaveType::factory()->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/hr/leave-requests', [
                'employee_id' => $employee->id,
                'leave_type_id' => $leaveType->id,
                'start_date' => now()->addDays(5)->toDateString(),
                'end_date' => now()->addDays(10)->toDateString(),
                'days_requested' => 5,
                'reason' => 'Vacation',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('hr_leave_requests', ['employee_id' => $employee->id]);
    });

    test('can approve leave request', function () {
        $request = LeaveRequest::factory()->create(['status' => 'pending']);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/hr/leave-requests/{$request->id}/approve", [
                'notes' => 'Approved',
            ]);

        $response->assertStatus(200);
        expect($request->fresh()->status)->toBe('approved');
        $this->assertDatabaseHas('hr_leave_approval_log', [
            'leave_request_id' => $request->id,
            'level' => 1,
            'action' => 'approved',
            'approver_role' => 'admin',
            'comment' => 'Approved',
        ]);
    });

    test('can reject leave request', function () {
        $request = LeaveRequest::factory()->create(['status' => 'pending']);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/hr/leave-requests/{$request->id}/reject", [
                'notes' => 'Not approved',
            ]);

        $response->assertStatus(200);
        expect($request->fresh()->status)->toBe('rejected');
        $this->assertDatabaseHas('hr_leave_approval_log', [
            'leave_request_id' => $request->id,
            'level' => 1,
            'action' => 'rejected',
            'approver_role' => 'admin',
        ]);
    });

    test('approval log level increments across repeated decisions on the same request', function () {
        $request = LeaveRequest::factory()->create(['status' => 'pending']);
        $secondApprover = Employee::factory()->create();

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/hr/leave-requests/{$request->id}/approve", ['notes' => 'Step 1']);

        // Simulate a second decision step on the same request (e.g. HR
        // countersigning after the manager) — level should increment, not
        // overwrite the first log row.
        app(\Modules\HR\Services\HRService::class)->approveLeave($request, $secondApprover->id, 'Step 2', 'hr_manager');

        $this->assertDatabaseCount('hr_leave_approval_log', 2);
        $this->assertDatabaseHas('hr_leave_approval_log', ['leave_request_id' => $request->id, 'level' => 1]);
        $this->assertDatabaseHas('hr_leave_approval_log', ['leave_request_id' => $request->id, 'level' => 2, 'approver_role' => 'hr_manager']);
    });

    test('can get pending leave requests', function () {
        LeaveRequest::factory()->count(3)->create(['status' => 'pending']);
        LeaveRequest::factory()->create(['status' => 'approved']);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/hr/leave-requests/pending');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    });
});
