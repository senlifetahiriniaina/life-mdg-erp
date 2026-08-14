<?php

namespace Modules\Validation\Tests\Feature;

use App\Models\User;
use Modules\Validation\Models\ApprovalRequest;
use Modules\Validation\Models\ApprovalWorkflow;
use Tests\TestCase;

/**
 * The web Show route existed but its {request} URI segment didn't match
 * the controller's $approval_request parameter name, so implicit route
 * model binding never resolved it — every visit would have fatalled with
 * a missing-argument TypeError. Never caught because nothing linked here
 * either (the page did its own broken fetch()+meta-token instead of using
 * the props the controller already builds).
 */
class ApprovalRequestWebTest extends TestCase
{
    public function test_show_renders_with_route_model_binding()
    {
        $user = User::factory()->create();
        $workflow = ApprovalWorkflow::create(['name' => 'Test Workflow', 'is_active' => true]);
        $request = ApprovalRequest::create([
            'workflow_id' => $workflow->id,
            'approvable_type' => 'App\\Models\\User',
            'approvable_id' => $user->id,
            'status' => 'pending',
            'requested_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->get("/approval-requests/{$request->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Validation/ApprovalRequests/Show', false)
            ->where('approvalRequest.id', $request->id)
            ->has('approvalRequest.can_approve')
        );
    }
}
