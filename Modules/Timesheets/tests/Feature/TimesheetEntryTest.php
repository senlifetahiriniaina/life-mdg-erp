<?php

declare(strict_types=1);

use App\Models\User;
use Modules\Timesheets\Models\TimesheetEntry;
use Spatie\Permission\Models\Role;


test('employee can create own timesheet entry', function () {
    $employee = User::factory()->create();

    $response = $this->actingAs($employee, 'sanctum')
        ->postJson('/api/v1/timesheets/entries', [
            'employee_id' => $employee->id,
            'entry_date' => now()->toDateString(),
            'hours_worked' => 8.0,
            'description' => 'Regular work',
        ]);

    $response->assertStatus(201);
});

test('employee can only see own timesheets', function () {
    $employee1 = User::factory()->create();
    $employee2 = User::factory()->create();

    TimesheetEntry::factory()->count(3)->create(['employee_id' => $employee1->id]);
    TimesheetEntry::factory()->count(2)->create(['employee_id' => $employee2->id]);

    $response = $this->actingAs($employee1, 'sanctum')
        ->getJson('/api/v1/timesheets/entries');

    $response->assertStatus(200);
});

test('manager can see all timesheets', function () {
    $manager = User::factory()->create();
    Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
    $manager->assignRole('manager');

    $employee1 = User::factory()->create();
    $employee2 = User::factory()->create();

    TimesheetEntry::factory()->count(3)->create(['employee_id' => $employee1->id]);
    TimesheetEntry::factory()->count(2)->create(['employee_id' => $employee2->id]);

    $response = $this->actingAs($manager, 'sanctum')
        ->getJson('/api/v1/timesheets/entries');

    $response->assertStatus(200);
});

test('employee can edit own draft timesheet', function () {
    $employee = User::factory()->create();
    $entry = TimesheetEntry::factory()->create([
        'employee_id' => $employee->id,
        'status' => 'draft',
        'hours_worked' => 8.0,
    ]);

    $response = $this->actingAs($employee, 'sanctum')
        ->putJson('/api/v1/timesheets/entries/'.$entry->id, [
            'hours_worked' => 9.0,
        ]);

    $response->assertStatus(200);
});

test('employee cannot edit submitted timesheet', function () {
    $employee = User::factory()->create();
    $entry = TimesheetEntry::factory()->create([
        'employee_id' => $employee->id,
        'status' => 'submitted',
    ]);

    $response = $this->actingAs($employee, 'sanctum')
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
    $employee = User::factory()->create();
    $entry = TimesheetEntry::factory()->create([
        'status' => 'submitted',
    ]);

    $response = $this->actingAs($employee, 'sanctum')
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
    $employee = User::factory()->create();
    $entry = TimesheetEntry::factory()->create([
        'employee_id' => $employee->id,
        'status' => 'draft',
    ]);

    $response = $this->actingAs($employee, 'sanctum')
        ->postJson('/api/v1/timesheets/entries/'.$entry->id.'/submit');

    $response->assertStatus(200);
});
