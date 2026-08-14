<?php

namespace Modules\Validation\Tests\Feature;

use App\Models\User;
use Modules\HR\Models\Employee;
use Modules\HR\Models\LeaveRequest;
use Modules\HR\Models\LeaveType;
use Modules\HR\Models\ShiftSchedule;
use Modules\Validation\Models\ApprovalHierarchy;
use Modules\Validation\Models\ApprovalRequest;
use Modules\Validation\Models\ApprovalRule;
use Modules\Validation\Models\ApprovalWorkflow;
use Modules\Validation\Models\HierarchyLevel;
use Modules\Validation\Models\LevelApprover;
use Modules\Validation\Services\ApprovalRoutingResolver;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ApprovalRoutingResolverTest extends TestCase
{
    protected ApprovalRoutingResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = app(ApprovalRoutingResolver::class);
    }

    protected function makeHierarchyWithLevel(array $levelOverrides = []): array
    {
        $hierarchy = ApprovalHierarchy::create([
            'name' => 'Test hierarchy',
            'module_name' => 'test.module',
            'is_active' => true,
        ]);

        $level = HierarchyLevel::create(array_merge([
            'hierarchy_id' => $hierarchy->id,
            'level_order' => 1,
            'title' => 'Level 1',
            'approver_count' => 1,
            'delegation_allowed' => true,
        ], $levelOverrides));

        return [$hierarchy, $level];
    }

    protected function makeApprovedLeave(int $employeeId, array $overrides = []): LeaveRequest
    {
        $leaveType = LeaveType::firstOrCreate(
            ['code' => 'ANNUAL'],
            ['name' => 'Annual leave', 'days_per_year' => 25]
        );

        return LeaveRequest::create(array_merge([
            'employee_id' => $employeeId,
            'leave_type_id' => $leaveType->id,
            'type' => 'annual',
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'days_requested' => 1,
            'status' => 'approved',
        ], $overrides));
    }

    protected function makeApprovalRequest(ApprovalHierarchy $hierarchy): ApprovalRequest
    {
        $workflow = ApprovalWorkflow::create([
            'name' => 'Test workflow',
            'module_name' => 'test.module',
            'is_active' => true,
        ]);

        return ApprovalRequest::create([
            'workflow_id' => $workflow->id,
            'approvable_type' => 'test',
            'approvable_id' => 1,
            'status' => 'pending',
            'requested_by' => User::factory()->create()->id,
            'hierarchy_id' => $hierarchy->id,
            'current_level' => 1,
        ]);
    }

    public function test_approver_with_no_shift_schedule_is_available()
    {
        $employee = Employee::factory()->create();

        $this->assertTrue($this->resolver->isAvailable($employee->user, now()));
    }

    public function test_approver_on_approved_leave_today_is_unavailable()
    {
        $employee = Employee::factory()->create();

        $this->makeApprovedLeave($employee->id, [
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'days_requested' => 3,
        ]);

        $this->assertFalse($this->resolver->isAvailable($employee->user, now()));
    }

    public function test_approver_outside_active_shift_hours_is_unavailable()
    {
        $employee = Employee::factory()->create();

        // Shift covers every day, but 00:00-00:01 — "now" will not fall in it.
        ShiftSchedule::create([
            'employee_id' => $employee->id,
            'shift_name' => 'Night micro-shift',
            'start_time' => '00:00:00',
            'end_time' => '00:01:00',
            'working_hours' => 0,
            'days_of_week' => [0, 1, 2, 3, 4, 5, 6],
            'status' => 'active',
            'effective_from' => now()->subDay()->toDateString(),
        ]);

        $this->assertFalse($this->resolver->isAvailable($employee->user, now()->setTime(12, 0)));
    }

    public function test_unavailable_approver_delegates_to_configured_backup()
    {
        [$hierarchy, $level] = $this->makeHierarchyWithLevel();

        $approverEmployee = Employee::factory()->create();
        $backupUser = User::factory()->create();

        $this->makeApprovedLeave($approverEmployee->id);

        LevelApprover::create([
            'hierarchy_level_id' => $level->id,
            'user_id' => $approverEmployee->user_id,
            'backup_user_id' => $backupUser->id,
            'is_active' => true,
        ]);

        $request = $this->makeApprovalRequest($hierarchy);

        $resolved = $this->resolver->resolveApprovers($request);

        $this->assertTrue($resolved->contains('id', $backupUser->id));
        $this->assertFalse($resolved->contains('id', $approverEmployee->user_id));

        $request->refresh();
        $this->assertEquals('leave', $request->escalation_reason);
        $this->assertEquals($approverEmployee->user_id, $request->escalated_from_id);
    }

    public function test_unavailable_approver_with_no_backup_escalates_to_next_level()
    {
        [$hierarchy, $level1] = $this->makeHierarchyWithLevel(['level_order' => 1]);

        $level2 = HierarchyLevel::create([
            'hierarchy_id' => $hierarchy->id,
            'level_order' => 2,
            'title' => 'Level 2',
            'approver_count' => 1,
        ]);

        $approverEmployee = Employee::factory()->create();
        $level2User = User::factory()->create();

        $this->makeApprovedLeave($approverEmployee->id);

        LevelApprover::create([
            'hierarchy_level_id' => $level1->id,
            'user_id' => $approverEmployee->user_id,
            'is_active' => true,
        ]);

        LevelApprover::create([
            'hierarchy_level_id' => $level2->id,
            'user_id' => $level2User->id,
            'is_active' => true,
        ]);

        $request = $this->makeApprovalRequest($hierarchy);

        $resolved = $this->resolver->resolveApprovers($request);

        $this->assertTrue($resolved->contains('id', $level2User->id));

        $request->refresh();
        $this->assertEquals(2, $request->current_level);
    }

    public function test_role_based_level_approver_resolves_to_all_role_holders()
    {
        [$hierarchy, $level] = $this->makeHierarchyWithLevel();

        Role::firstOrCreate(['name' => 'test-approver-role', 'guard_name' => 'web']);
        $roleUserA = User::factory()->create();
        $roleUserB = User::factory()->create();
        $roleUserA->assignRole('test-approver-role');
        $roleUserB->assignRole('test-approver-role');

        LevelApprover::create([
            'hierarchy_level_id' => $level->id,
            'role' => 'test-approver-role',
            'is_active' => true,
        ]);

        $request = $this->makeApprovalRequest($hierarchy);

        $resolved = $this->resolver->resolveApprovers($request);

        $this->assertTrue($resolved->contains('id', $roleUserA->id));
        $this->assertTrue($resolved->contains('id', $roleUserB->id));
        $this->assertCount(2, $resolved);
    }

    public function test_evaluate_condition_never_uses_eval()
    {
        // Strip comments/docblocks first — this class's own code intentionally
        // documents (in prose) that it no longer uses eval(), which would
        // otherwise false-positive a naive substring search.
        $source = file_get_contents(base_path('Modules/Validation/app/Models/ApprovalRule.php'));
        $tokens = token_get_all($source);
        $code = '';

        foreach ($tokens as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT])) {
                continue;
            }
            $code .= is_array($token) ? $token[1] : $token;
        }

        $this->assertStringNotContainsString('eval(', $code);
    }
}
