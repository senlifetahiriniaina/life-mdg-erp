<?php

namespace Modules\HR\Tests\Feature;

use Tests\TestCase;
use Modules\HR\Services\SkillMatrixService;
use Modules\HR\Models\Skill;
use Modules\HR\Models\Employee;
use Modules\HR\Models\Department;
use App\Models\User;

class SkillMatrixServiceTest extends TestCase
{
    private SkillMatrixService $service;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SkillMatrixService::class);
        $this->user = User::factory()->create();
    }

    /** @test */
    public function it_assigns_skill_to_employee()
    {
        $skill = Skill::factory()->create(['tenant_id' => $this->user->tenant_id]);
        $employee = Employee::factory()->create(['tenant_id' => $this->user->tenant_id]);

        $empSkill = $this->service->assignSkill(
            employeeId: $employee->id,
            skillId: $skill->id,
            currentLevel: 2,
            targetLevel: 4
        );

        $this->assertEquals(2, $empSkill->current_level);
        $this->assertEquals(4, $empSkill->target_level);
    }

    /** @test */
    public function it_calculates_skill_gap()
    {
        $dept = Department::factory()->create(['tenant_id' => $this->user->tenant_id]);
        $skill = Skill::factory()->create(['tenant_id' => $this->user->tenant_id, 'is_mandatory' => true]);
        $emp = Employee::factory()->create(['tenant_id' => $this->user->tenant_id, 'department_id' => $dept->id]);

        $this->service->assignSkill($emp->id, $skill->id, currentLevel: 2, targetLevel: 4);

        $gaps = $this->service->getSkillGaps($dept->id);
        
        $this->assertNotEmpty($gaps);
        $this->assertEquals(2, $gaps->first()->gap);
    }

    /** @test */
    public function it_identifies_critical_gaps()
    {
        $dept = Department::factory()->create(['tenant_id' => $this->user->tenant_id]);
        $skill = Skill::factory()->create(['tenant_id' => $this->user->tenant_id, 'is_mandatory' => true]);
        $emp = Employee::factory()->create(['tenant_id' => $this->user->tenant_id, 'department_id' => $dept->id]);

        $this->service->assignSkill($emp->id, $skill->id, currentLevel: 1); // Below proficient

        $criticalGaps = $this->service->getCriticalGaps($dept->id);

        $this->assertNotEmpty($criticalGaps);
    }

    /** @test */
    public function it_gets_employees_by_skill()
    {
        $skill = Skill::factory()->create(['tenant_id' => $this->user->tenant_id]);
        $emp1 = Employee::factory()->create(['tenant_id' => $this->user->tenant_id]);
        $emp2 = Employee::factory()->create(['tenant_id' => $this->user->tenant_id]);

        $this->service->assignSkill($emp1->id, $skill->id, currentLevel: 4);
        $this->service->assignSkill($emp2->id, $skill->id, currentLevel: 2);

        $employees = $this->service->getEmployeesBySkill($skill->id, minLevel: 3);

        $this->assertCount(1, $employees);
        $this->assertEquals($emp1->id, $employees->first()->id);
    }

    /** @test */
    public function it_calculates_coverage_percentage()
    {
        $dept = Department::factory()->create(['tenant_id' => $this->user->tenant_id]);
        $skill = Skill::factory()->create(['tenant_id' => $this->user->tenant_id, 'is_mandatory' => true]);
        $emp = Employee::factory()->create(['tenant_id' => $this->user->tenant_id, 'department_id' => $dept->id]);

        // Low coverage
        $this->service->assignSkill($emp->id, $skill->id, currentLevel: 1);
        $coverage = $this->service->calculateCoveragePct($dept->id);
        
        $this->assertLessThan(100, $coverage);
    }

    /** @test */
    public function it_exports_matrix_as_csv()
    {
        $dept = Department::factory()->create(['tenant_id' => $this->user->tenant_id]);
        $skill = Skill::factory()->create(['tenant_id' => $this->user->tenant_id]);
        $emp = Employee::factory()->create(['tenant_id' => $this->user->tenant_id, 'department_id' => $dept->id]);

        $this->service->assignSkill($emp->id, $skill->id, currentLevel: 3);

        $csv = $this->service->exportMatrix($dept->id);

        $this->assertStringContainsString('Employee', $csv);
        $this->assertStringContainsString($emp->first_name, $csv);
    }
}
