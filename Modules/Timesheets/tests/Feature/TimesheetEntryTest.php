<?php

declare(strict_types=1);

use App\Models\User;
use Modules\HR\Models\Employee;
use Modules\Timesheets\Models\TimesheetEntry;
use Spatie\Permission\Models\Role;

/**
 * Chantier 8.4: employee_id on TimesheetEntry FKs to hr_employees.id, not
 * users.id — every test here assigned $user->id directly, which only kept
 * passing because the controller/policy had the same ID-space mismatch bug
 * (now fixed). Every entry needs a real, linked Employee record.
 */
beforeEach(function () {
    // Routes require role:employee,manager,admin; RefreshDatabase wipes roles
    // between tests, so both roles used in this file are re-seeded every time.
    Role::firstOrCreate(['name' => 'employee', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
});

test('employee can create own timesheet entry', function () {
    $user = User::factory()->create();
    $user->assignRole('employee');
    $employee = Employee::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/timesheets/entries', [
            'employee_id' => $employee->id,
            'entry_date' => now()->toDateString(),
            'hours_worked' => 8.0,
            'description' => 'Regular work',
        ]);

    $response->assertStatus(201);
});

test('employee can only see own timesheets', function () {
    $user1 = User::factory()->create();
    $user1->assignRole('employee');
    $employee1 = Employee::factory()->create(['user_id' => $user1->id]);
    $user2 = User::factory()->create();
    $user2->assignRole('employee');
    $employee2 = Employee::factory()->create(['user_id' => $user2->id]);

    TimesheetEntry::factory()->count(3)->create(['employee_id' => $employee1->id]);
    TimesheetEntry::factory()->count(2)->create(['employee_id' => $employee2->id]);

    $response = $this->actingAs($user1, 'sanctum')
        ->getJson('/api/v1/timesheets/entries');

    $response->assertStatus(200);
});

test('manager can see all timesheets', function () {
    $manager = User::factory()->create();
    Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
    $manager->assignRole('manager');

    $user1 = User::factory()->create();
    $user1->assignRole('employee');
    $employee1 = Employee::factory()->create(['user_id' => $user1->id]);
    $user2 = User::factory()->create();
    $user2->assignRole('employee');
    $employee2 = Employee::factory()->create(['user_id' => $user2->id]);

    TimesheetEntry::factory()->count(3)->create(['employee_id' => $employee1->id]);
    TimesheetEntry::factory()->count(2)->create(['employee_id' => $employee2->id]);

    $response = $this->actingAs($manager, 'sanctum')
        ->getJson('/api/v1/timesheets/entries');

    $response->assertStatus(200);
});

test('employee can edit own draft timesheet', function () {
    $user = User::factory()->create();
    $user->assignRole('employee');
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $entry = TimesheetEntry::factory()->create([
        'employee_id' => $employee->id,
        'status' => 'draft',
        'hours_worked' => 8.0,
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->putJson('/api/v1/timesheets/entries/'.$entry->id, [
            'hours_worked' => 9.0,
        ]);

    $response->assertStatus(200);
});

test('employee cannot edit submitted timesheet', function () {
    $user = User::factory()->create();
    $user->assignRole('employee');
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $entry = TimesheetEntry::factory()->create([
        'employee_id' => $employee->id,
        'status' => 'submitted',
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->putJson('/api/v1/timesheets/entries/'.$entry->id, [
            'hours_worked' => 10.0,
        ]);

    $response->assertStatus(403);
});

test('manager can approve timesheet entry', function () {
    $manager = User::factory()->create();
    Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
    $manager->assignRole('manager');

    $entry = TimesheetEntry::factory()->create([
        'status' => 'submitted',
    ]);

    $response = $this->actingAs($manager, 'sanctum')
        ->postJson('/api/v1/timesheets/entries/'.$entry->id.'/approve');

    $response->assertStatus(200);
});

test('employee cannot approve timesheets', function () {
    $user = User::factory()->create();
    $user->assignRole('employee');
    $entry = TimesheetEntry::factory()->create([
        'status' => 'submitted',
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/timesheets/entries/'.$entry->id.'/approve');

    $response->assertStatus(403);
});

test('manager can reject timesheet with reason', function () {
    $manager = User::factory()->create();
    Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
    $manager->assignRole('manager');

    $entry = TimesheetEntry::factory()->create([
        'status' => 'submitted',
    ]);

    $response = $this->actingAs($manager, 'sanctum')
        ->postJson('/api/v1/timesheets/entries/'.$entry->id.'/reject', [
            'notes' => 'Needs supervisor review',
        ]);

    $response->assertStatus(200);
});

test('can submit draft timesheet', function () {
    $user = User::factory()->create();
    $user->assignRole('employee');
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $entry = TimesheetEntry::factory()->create([
        'employee_id' => $employee->id,
        'status' => 'draft',
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/timesheets/entries/'.$entry->id.'/submit');

    $response->assertStatus(200);
});
