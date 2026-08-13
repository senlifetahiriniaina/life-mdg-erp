<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\HR\Models\Employee;

uses(RefreshDatabase::class);

test('guest is redirected to login from employees index', function () {
    $this->get('/hr/employees')->assertRedirect('/login');
});

test('guest is redirected to login from employee show', function () {
    $employee = Employee::factory()->create();
    $this->get("/hr/employees/{$employee->id}")->assertRedirect('/login');
});

test('authenticated user sees employees index', function () {
    $user = User::factory()->create();
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
    $user = User::factory()->create();
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
    $user     = User::factory()->create();
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
    $user = User::factory()->create();
    $this->actingAs($user)->get('/hr/employees/99999')->assertNotFound();
});
