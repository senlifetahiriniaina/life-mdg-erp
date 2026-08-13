<?php

declare(strict_types=1);

use App\Models\User;
use Modules\Validation\Models\ApprovalRequest;
use Modules\Validation\Models\ApprovalWorkflow;
use Spatie\Permission\Models\Role;


test('can create approval workflow', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/validation/workflows', [
            'name' => 'Invoice Approval',
            'is_active' => true,
        ]);

    $response->assertStatus(201);
});

test('can approve request with proper authorization', function () {
    $approver = User::factory()->create();
    Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
    $approver->assignRole('manager');

    $workflow = ApprovalWorkflow::factory()->create(['is_active' => true]);
    $request = ApprovalRequest::factory()->create([
        'workflow_id' => $workflow->id,
        'approver_id' => $approver->id,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($approver, 'sanctum')
        ->postJson('/api/v1/validation/requests/'.$request->id.'/approve', [
            'comment' => 'Approved',
        ]);

    $response->assertStatus(200);
});

test('unauthorized user cannot approve request', function () {
    $unauthorized = User::factory()->create();
    $approver = User::factory()->create();

    $workflow = ApprovalWorkflow::factory()->create();
    $request = ApprovalRequest::factory()->create([
        'workflow_id' => $workflow->id,
        'approver_id' => $approver->id,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($unauthorized, 'sanctum')
        ->postJson('/api/v1/validation/requests/'.$request->id.'/approve');

    $response->assertStatus(403);
});

test('can reject request with authorization', function () {
    $approver = User::factory()->create();
    Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
    $approver->assignRole('manager');

    $workflow = ApprovalWorkflow::factory()->create();
    $request = ApprovalRequest::factory()->create([
        'workflow_id' => $workflow->id,
        'approver_id' => $approver->id,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($approver, 'sanctum')
        ->postJson('/api/v1/validation/requests/'.$request->id.'/reject', [
            'reason' => 'Does not meet criteria',
        ]);

    $response->assertStatus(200);
});

test('can view pending approvals for manager', function () {
    $manager = User::factory()->create();
    Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
    $manager->assignRole('manager');

    $workflow = ApprovalWorkflow::factory()->create();

    ApprovalRequest::factory()->count(3)->create([
        'workflow_id' => $workflow->id,
        'approver_id' => $manager->id,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($manager, 'sanctum')
        ->getJson('/api/v1/validation/requests?status=pending');

    $response->assertStatus(200);
});
