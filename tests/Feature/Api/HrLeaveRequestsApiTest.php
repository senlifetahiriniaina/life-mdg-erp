<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\HR\Models\LeaveRequest;
use Modules\HR\Models\Employee;

uses(RefreshDatabase::class);

// ── Auth & Authorization ──────────────────────────────────────────────────────

test('unauthenticated requests are rejected', function () {
    $this->getJson('/api/v1/hr/leave-requests')->assertUnauthorized();
    $this->postJson('/api/v1/hr/leave-requests', [])->assertUnauthorized();
});

test('employee can request leave', function () {
    $user = actingAsUser('employee');
    $this->postJson('/api/v1/hr/leave-requests', [
        'type'       => 'annual',
        'start_date' => now()->addDays(7)->toDateString(),
        'end_date'   => now()->addDays(9)->toDateString(),
    ])->assertCreated();
});

test('hr-manager can approve/reject leave', function () {
    $user = actingAsUser('employee');
    $leave = LeaveRequest::factory()->create(['status' => 'pending']);

    $manager = actingAsUser('hr-manager');
    $this->postJson("/api/v1/hr/leave-requests/{$leave->id}/approve")->assertOk();
    expect($leave->fresh()->status)->toBe('approved');
});

// ── Index ─────────────────────────────────────────────────────────────────────

test('index returns paginated leave requests', function () {
    $user = actingAsUser('employee');
    LeaveRequest::factory()->count(15)->create();
    $response = $this
        ->getJson('/api/v1/hr/leave-requests')
        ->assertOk()
        ->assertJsonStructure(['data', 'meta' => ['total', 'per_page']]);
    expect($response->json('meta.total'))->toBe(15);
});

test('index filters by status', function () {
    $user = actingAsUser('employee');
    LeaveRequest::factory()->count(5)->create(['status' => 'approved']);
    LeaveRequest::factory()->count(3)->create(['status' => 'pending']);
    $response = $this
        ->getJson('/api/v1/hr/leave-requests?status=approved')
        ->assertOk();
    expect($response->json('meta.total'))->toBe(5);
});

test('index filters by type', function () {
    $user = actingAsUser('employee');
    LeaveRequest::factory()->count(4)->create(['type' => 'annual']);
    LeaveRequest::factory()->count(2)->create(['type' => 'sick']);
    $response = $this
        ->getJson('/api/v1/hr/leave-requests?type=annual')
        ->assertOk();
    expect($response->json('meta.total'))->toBe(4);
});

test('index filters by employee', function () {
    $user = actingAsUser('employee');
    $employee = Employee::factory()->create();
    LeaveRequest::factory()->create(['employee_id' => $employee->id]);
    LeaveRequest::factory()->create();

    $response = $this
        ->getJson("/api/v1/hr/leave-requests?employee_id={$employee->id}")
        ->assertOk();
    expect($response->json('meta.total'))->toBe(1);
});

test('index filters by date range', function () {
    $user = actingAsUser('employee');
    LeaveRequest::factory()->create([
        'start_date' => now()->addDays(5)->toDateString(),
        'end_date'   => now()->addDays(7)->toDateString(),
    ]);
    LeaveRequest::factory()->create([
        'start_date' => now()->addMonths(2)->toDateString(),
        'end_date'   => now()->addMonths(2)->addDays(2)->toDateString(),
    ]);

    $response = $this
        ->getJson('/api/v1/hr/leave-requests?start_date_before=' . now()->addMonths(1)->toDateString())
        ->assertOk();
    expect($response->json('meta.total'))->toBe(1);
});

// ── Store ─────────────────────────────────────────────────────────────────────

test('can create leave request with required fields', function () {
    $user = actingAsUser('employee');
    $start = now()->addDays(10)->toDateString();
    $end = now()->addDays(12)->toDateString();
    $response = $this
        ->postJson('/api/v1/hr/leave-requests', [
            'type'       => 'annual',
            'start_date' => $start,
            'end_date'   => $end,
        ])
        ->assertCreated();
    $leave = LeaveRequest::find($response->json('id'));
    expect($leave->type)->toBe('annual');
    expect($leave->days)->toBe(3); // 3 days inclusive
});

test('can create leave request with reason', function () {
    $user = actingAsUser('employee');
    $response = $this
        ->postJson('/api/v1/hr/leave-requests', [
            'type'       => 'annual',
            'start_date' => now()->addDays(10)->toDateString(),
            'end_date'   => now()->addDays(12)->toDateString(),
            'reason'     => 'Family vacation',
        ])
        ->assertCreated()
        ->assertJsonFragment(['reason' => 'Family vacation']);
});

test('create requires type and dates', function () {
    $user = actingAsUser('employee');
    $this->postJson('/api/v1/hr/leave-requests', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['type', 'start_date', 'end_date']);
});

test('create validates date format', function () {
    $user = actingAsUser('employee');
    $this->postJson('/api/v1/hr/leave-requests', [
        'type'       => 'annual',
        'start_date' => 'invalid-date',
        'end_date'   => now()->toDateString(),
    ])->assertUnprocessable()->assertJsonValidationErrors(['start_date']);
});

test('create validates end_date after start_date', function () {
    $user = actingAsUser('employee');
    $this->postJson('/api/v1/hr/leave-requests', [
        'type'       => 'annual',
        'start_date' => now()->addDays(10)->toDateString(),
        'end_date'   => now()->addDays(5)->toDateString(), // Before start
    ])->assertUnprocessable()->assertJsonValidationErrors(['end_date']);
});

test('create validates start_date is in future', function () {
    $user = actingAsUser('employee');
    $this->postJson('/api/v1/hr/leave-requests', [
        'type'       => 'annual',
        'start_date' => now()->subDays(1)->toDateString(),
        'end_date'   => now()->addDays(1)->toDateString(),
    ])->assertUnprocessable()->assertJsonValidationErrors(['start_date']);
});

test('created leave request defaults to pending status', function () {
    $user = actingAsUser('employee');
    $response = $this
        ->postJson('/api/v1/hr/leave-requests', [
            'type'       => 'annual',
            'start_date' => now()->addDays(10)->toDateString(),
            'end_date'   => now()->addDays(12)->toDateString(),
        ])
        ->assertCreated();
    expect($response->json('status'))->toBe('pending');
});

test('created leave request belongs to authenticated employee', function () {
    $user = actingAsUser('employee');
    $response = $this
        ->postJson('/api/v1/hr/leave-requests', [
            'type'       => 'annual',
            'start_date' => now()->addDays(10)->toDateString(),
            'end_date'   => now()->addDays(12)->toDateString(),
        ])
        ->assertCreated();
    $leave = LeaveRequest::find($response->json('id'));
    expect($leave->employee_id)->toBe($user->employee->id);
});

// ── Show ──────────────────────────────────────────────────────────────────────

test('can show leave request', function () {
    $user = actingAsUser('employee');
    $leave = LeaveRequest::factory()->create();
    $response = $this
        ->getJson("/api/v1/hr/leave-requests/{$leave->id}")
        ->assertOk()
        ->assertJsonFragment(['id' => $leave->id]);
});

test('show returns 404 for missing leave request', function () {
    $user = actingAsUser('employee');
    $this->getJson('/api/v1/hr/leave-requests/99999')->assertNotFound();
});

// ── Update ────────────────────────────────────────────────────────────────────

test('can update pending leave request', function () {
    // Chantier 8.3: update() now requires ownership (or an elevated role) — an
    // unrelated 'employee' could previously edit anyone's pending leave request.
    $user = actingAsUser('employee');
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $leave = LeaveRequest::factory()->create(['employee_id' => $employee->id, 'status' => 'pending', 'reason' => 'Old reason']);
    $this
        ->putJson("/api/v1/hr/leave-requests/{$leave->id}", ['reason' => 'New reason'])
        ->assertOk();
    expect($leave->fresh()->reason)->toBe('New reason');
});

test('cannot update approved leave request', function () {
    $user = actingAsUser('employee');
    $leave = LeaveRequest::factory()->create(['status' => 'approved']);
    $this->putJson("/api/v1/hr/leave-requests/{$leave->id}", ['reason' => 'Updated'])
        ->assertForbidden();
});

test('can update dates on pending request', function () {
    $user = actingAsUser('employee');
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $leave = LeaveRequest::factory()->create(['employee_id' => $employee->id, 'status' => 'pending']);
    $newStart = now()->addDays(15)->toDateString();
    $newEnd = now()->addDays(17)->toDateString();
    $this
        ->putJson("/api/v1/hr/leave-requests/{$leave->id}", [
            'start_date' => $newStart,
            'end_date'   => $newEnd,
        ])
        ->assertOk();
});

// ── Approval Actions ──────────────────────────────────────────────────────────

test('hr-manager can approve pending leave', function () {
    $user = actingAsUser('employee');
    $leave = LeaveRequest::factory()->create(['status' => 'pending']);

    $manager = actingAsUser('hr-manager');
    $this->postJson("/api/v1/hr/leave-requests/{$leave->id}/approve")
        ->assertOk();
    expect($leave->fresh()->status)->toBe('approved');
});

test('hr-manager can reject pending leave', function () {
    $user = actingAsUser('employee');
    $leave = LeaveRequest::factory()->create(['status' => 'pending']);

    $manager = actingAsUser('hr-manager');
    $this->postJson("/api/v1/hr/leave-requests/{$leave->id}/reject", [
        'rejection_reason' => 'Cannot approve at this time',
    ])->assertOk();
    expect($leave->fresh()->status)->toBe('rejected');
});

test('cannot approve already approved leave', function () {
    $user = actingAsUser('employee');
    $leave = LeaveRequest::factory()->create(['status' => 'approved']);

    $manager = actingAsUser('hr-manager');
    $this->postJson("/api/v1/hr/leave-requests/{$leave->id}/approve")
        ->assertForbidden();
});

// ── Destroy ───────────────────────────────────────────────────────────────────

test('can cancel pending leave request', function () {
    // Chantier 8.3: delete() is role-only (manager/hr-manager/admin) by design —
    // cancelling was never a self-service action, only reading/creating/updating are.
    $user = actingAsUser('hr-manager');
    $leave = LeaveRequest::factory()->create(['status' => 'pending']);
    $this->deleteJson("/api/v1/hr/leave-requests/{$leave->id}")->assertNoContent();
    expect($leave->fresh()->status)->toBe('cancelled');
});

test('cannot cancel approved leave request', function () {
    $user = actingAsUser('employee');
    $leave = LeaveRequest::factory()->create(['status' => 'approved']);
    $this->deleteJson("/api/v1/hr/leave-requests/{$leave->id}")->assertForbidden();
});
