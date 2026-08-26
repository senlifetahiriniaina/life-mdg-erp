<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\HR\Models\Employee;

uses(RefreshDatabase::class);

/**
 * /hr/employees is gated by module:HR + role:employee,hr-manager,
 * payroll-officer,manager,admin (since Chantier 8.3hp) — a bare unroled
 * User::factory() user now correctly 403s. Same seed-guard pattern used
 * throughout Modules/HR/tests for this exact bug class.
 */
function hrWebTestUser(): User
{
    if (\Spatie\Permission\Models\Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }
    $user = User::factory()->create();
    $user->assignRole('employee');

    return $user;
}

test('guest is redirected to login from employees index', function () {
    $this->get('/hr/employees')->assertRedirect('/login');
});

test('guest is redirected to login from employee show', function () {
    $employee = Employee::factory()->create();
    $this->get("/hr/employees/{$employee->id}")->assertRedirect('/login');
});

test('authenticated user sees employees index', function () {
    $user = hrWebTestUser();
    Employee::factory()->create();

    $this->actingAs($user)
        ->get('/hr/employees')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('HR/Employees/Index')
            ->has('employees')
        );
});

test('employees index returns paginated employees', function () {
    $user = hrWebTestUser();
    Employee::factory()->count(3)->create();

    $this->actingAs($user)
        ->get('/hr/employees')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('HR/Employees/Index')
            ->has('employees.data', 3)
        );
});

test('show page loads correct employee', function () {
    $user     = hrWebTestUser();
    $employee = Employee::factory()->create();

    $this->actingAs($user)
        ->get("/hr/employees/{$employee->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('HR/Employees/Show')
            ->has('employee')
        );
});

test('show for non-existent employee returns 404', function () {
    $user = hrWebTestUser();
    $this->actingAs($user)->get('/hr/employees/99999')->assertNotFound();
});
