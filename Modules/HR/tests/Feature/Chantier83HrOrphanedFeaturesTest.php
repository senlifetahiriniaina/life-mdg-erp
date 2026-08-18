<?php

declare(strict_types=1);

use App\Models\User;
use Modules\HR\Models\Employee;
use Modules\HR\Models\EmployeeSkill;
use Modules\HR\Models\SalaryBand;
use Modules\HR\Models\Skill;

/**
 * Chantier 8.3 (HR): several real controller methods and Vue pages had zero
 * route pointing at them. Covers: SalaryBandController::equityAnalysis(),
 * SkillController::employeeSkills(), EmployeeWebController::create/edit/portal,
 * and the self-fetching Departments/Leaves/Channels-style web pages.
 */
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function hrOrphanedFeaturesTestUser(): User
{
    if (\Spatie\Permission\Models\Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }
    $user = User::factory()->create();
    $user->assignRole('employee');

    return $user;
}

test('salary-bands/equity endpoint is real and reachable', function () {
    $user = hrOrphanedFeaturesTestUser();
    SalaryBand::factory()->create();

    $response = test()->actingAs($user, 'sanctum')->getJson('/api/v1/hr/salary-bands/equity');

    $response->assertOk();
    expect($response->json())->toHaveKey('analysis');
});

test('employees/{id}/skills endpoint lists an employee real skills', function () {
    $user = hrOrphanedFeaturesTestUser();
    $employee = Employee::factory()->create();
    $skill = Skill::factory()->create();
    EmployeeSkill::factory()->create(['employee_id' => $employee->id, 'skill_id' => $skill->id]);

    $response = test()->actingAs($user, 'sanctum')->getJson("/api/v1/hr/employees/{$employee->id}/skills");

    $response->assertOk();
    expect($response->json())->toHaveCount(1);
});

test('employees create and edit pages render', function () {
    $user = hrOrphanedFeaturesTestUser();
    $employee = Employee::factory()->create();

    test()->actingAs($user)->get('/hr/employees/create')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('HR/Employees/Form', false));

    test()->actingAs($user)->get("/hr/employees/{$employee->id}/edit")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('HR/Employees/Form', false)
            ->has('employee'));
});

test('portal page renders', function () {
    $user = hrOrphanedFeaturesTestUser();
    Employee::factory()->create(['user_id' => $user->id]);

    test()->actingAs($user)->get('/hr/portal')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('HR/Portal', false));
});

test('departments and leaves self-fetch pages render', function () {
    $user = hrOrphanedFeaturesTestUser();

    test()->actingAs($user)->get('/hr/departments')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('HR/Departments/Index', false));

    test()->actingAs($user)->get('/hr/leaves')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('HR/Leaves/Index', false));
});

test('attendance manage page renders at its own distinct route', function () {
    $user = hrOrphanedFeaturesTestUser();

    test()->actingAs($user)->get('/hr/attendance/manage')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('HR/Attendance/Manage', false));
});

test('departments index exposes manager and status, not is_active/parent', function () {
    $user = hrOrphanedFeaturesTestUser();
    $manager = Employee::factory()->create(['first_name' => 'Awa', 'last_name' => 'Koné']);
    $ourDept = \Modules\HR\Models\Department::factory()->create(['manager_id' => $manager->id, 'status' => 'active']);

    $response = test()->actingAs($user, 'sanctum')->getJson('/api/v1/hr/departments?per_page=200');

    $response->assertOk();
    // Employee::factory() incidentally creates its own department + job-position
    // (each with its own department_id closure) — find ours by id rather than
    // assuming it is first in the (name-ordered) list.
    $dept = collect($response->json('data'))->firstWhere('id', $ourDept->id);
    expect($dept['manager']['full_name'])->toBe('Awa Koné');
    expect($dept['status'])->toBe('active');
});
