<?php

namespace Modules\Validation\Tests\Feature;

use App\Models\User;
use Modules\Validation\Models\ApprovalWorkflow;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Modules/Validation's structural mutation routes (workflows/rules/
 * hierarchies) had NO role: middleware at all — any authenticated user
 * could create/edit/delete approval configuration. The day-to-day
 * approve/reject/delegate actions are deliberately left ungated here
 * (ApprovalRequestPolicy already restricts those per-request).
 */
class ValidationRbacTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
    }

    public function test_non_admin_cannot_create_a_workflow()
    {
        $user = User::factory()->create();
        $user->assignRole('manager');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/validation/approval-workflows', [
                'name' => 'Test Workflow',
                'module_name' => 'Achats',
            ]);

        $response->assertStatus(403);
    }

    public function test_admin_can_create_a_workflow()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/validation/approval-workflows', [
                'name' => 'Test Workflow',
                'module_name' => 'Achats',
            ]);

        $response->assertCreated();
    }

    public function test_non_admin_cannot_delete_a_workflow()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $workflow = ApprovalWorkflow::create([
            'name' => 'Existing',
            'module_name' => 'Achats',
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        $manager = User::factory()->create();
        $manager->assignRole('manager');

        $response = $this->actingAs($manager, 'sanctum')
            ->deleteJson("/api/v1/validation/approval-workflows/{$workflow->id}");

        $response->assertStatus(403);
    }

    public function test_reading_workflows_remains_open_to_any_authenticated_user()
    {
        $user = User::factory()->create();
        $user->assignRole('manager');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/validation/approval-workflows')
            ->assertOk();
    }
}
