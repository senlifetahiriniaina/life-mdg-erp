<?php

declare(strict_types=1);

namespace Modules\Validation\Tests\Feature;

use App\Models\User;
use Modules\Validation\Models\ApprovalRequest;
use Modules\Validation\Models\ApprovalWorkflow;
use Modules\Validation\Models\ValidationRule;
use Tests\TestCase;

/**
 * Chantier 6: Workflows/Index.vue, Workflows/Builder.vue, and
 * ApprovalRequests/Index.vue all used a document.querySelector('meta[name=
 * "api-token"]').content Bearer-token pattern that crashes immediately —
 * no such meta tag exists anywhere in this app (session/cookie auth via
 * Sanctum, not bearer tokens). Rewired onto axios. ValidationRules/Index.vue
 * is a new screen for the generic data-validation rule engine.
 */
class ValidationScreensWebTest extends TestCase
{
    public function test_workflows_index_renders()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/workflows');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Validation/Workflows/Index', false));
    }

    public function test_workflows_create_renders()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/workflows/builder');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Validation/Workflows/Builder', false));
    }

    public function test_workflows_builder_edit_renders()
    {
        $user = User::factory()->create();
        $workflow = ApprovalWorkflow::factory()->create();

        $response = $this->actingAs($user)->get("/workflows/{$workflow->id}/builder");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Validation/Workflows/Builder', false));
    }

    public function test_approval_requests_index_renders()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/approval-requests');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Validation/ApprovalRequests/Index', false));
    }

    public function test_validation_rules_index_renders()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/validation-rules');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Validation/ValidationRules/Index', false));
    }

    /**
     * ApprovalWorkflowController::index() used to hard-filter is_active=true,
     * which meant deactivating a workflow made it disappear from the list
     * permanently — the Deactivate/Activate toggle in Workflows/Index.vue
     * could never be reversed. Regression-tests the fix.
     */
    public function test_approval_workflows_api_lists_inactive_workflows_too()
    {
        $user = User::factory()->create();
        ApprovalWorkflow::factory()->create(['is_active' => true]);
        ApprovalWorkflow::factory()->create(['is_active' => false]);

        $response = $this->actingAs($user)->getJson('/api/v1/validation/approval-workflows');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    /**
     * ApprovalRequestController::index() ignored a `module` query param
     * entirely and never computed awaiting_my_action — both now used by
     * the rewired ApprovalRequests/Index.vue.
     */
    public function test_approval_requests_api_filters_by_module_and_computes_awaiting_my_action()
    {
        $user = User::factory()->create();

        $accountingWorkflow = ApprovalWorkflow::factory()->create(['module_name' => 'Accounting']);
        $achatsWorkflow = ApprovalWorkflow::factory()->create(['module_name' => 'Achats']);

        $mine = ApprovalRequest::factory()->create([
            'workflow_id' => $accountingWorkflow->id,
            'status' => 'pending',
            'approver_id' => $user->id,
        ]);
        ApprovalRequest::factory()->create([
            'workflow_id' => $accountingWorkflow->id,
            'status' => 'pending',
            'approver_id' => User::factory()->create()->id,
        ]);
        ApprovalRequest::factory()->create(['workflow_id' => $achatsWorkflow->id]);

        $response = $this->actingAs($user)->getJson('/api/v1/validation/approval-requests?module=Accounting');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));

        $mineRow = collect($response->json('data'))->firstWhere('id', $mine->id);
        $this->assertTrue($mineRow['awaiting_my_action']);
    }

    public function test_validation_rule_crud()
    {
        $user = User::factory()->create();

        $create = $this->actingAs($user)->postJson('/api/v1/validation-rules', [
            'name' => 'Email requis',
            'field' => 'email',
            'type' => 'email',
        ]);
        $create->assertCreated();
        $ruleId = $create->json('data.id');

        $index = $this->actingAs($user)->getJson('/api/v1/validation-rules');
        $index->assertOk();
        $this->assertContains($ruleId, collect($index->json('data'))->pluck('id')->all());

        $update = $this->actingAs($user)->putJson("/api/v1/validation-rules/{$ruleId}", ['name' => 'Email obligatoire']);
        $update->assertOk();
        $this->assertDatabaseHas('validation_rules', ['id' => $ruleId, 'name' => 'Email obligatoire']);

        $delete = $this->actingAs($user)->deleteJson("/api/v1/validation-rules/{$ruleId}");
        $delete->assertNoContent();
    }
}
