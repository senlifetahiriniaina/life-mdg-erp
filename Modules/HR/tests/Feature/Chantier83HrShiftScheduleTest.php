<?php

declare(strict_types=1);

use App\Models\User;
use Modules\HR\Models\Employee;
use Modules\HR\Models\ShiftSchedule;

/**
 * Chantier 8.3 (HR): Shifts/Schedule.vue previously modeled a per-date
 * monthly calendar, but the only real backend (ShiftSchedule) models
 * recurring weekly templates (days_of_week + effective_from/to) with no
 * per-date instance concept — rewritten to manage the real template shape.
 * Also fixes AttendanceBiometricController::createShift() silently dropping
 * is_flexible/effective_to/notes (real ShiftSchedule columns missing from
 * validation).
 */
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function hrShiftScheduleUser(): User
{
    if (\Spatie\Permission\Models\Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }
    $user = User::factory()->create();
    $user->assignRole('hr-manager');

    return $user;
}

test('shifts/schedule page renders', function () {
    $user = hrShiftScheduleUser();

    test()->actingAs($user)->get('/hr/shifts/schedule')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('HR/Shifts/Schedule', false));
});

test('creating a shift persists is_flexible/effective_to/notes', function () {
    $user = hrShiftScheduleUser();
    $employee = Employee::factory()->create();

    $response = test()->actingAs($user, 'sanctum')->postJson('/api/v1/hr/shifts', [
        'employee_id' => $employee->id,
        'shift_name' => 'Morning',
        'start_time' => '09:00:00',
        'end_time' => '17:00:00',
        'working_hours' => 8,
        'days_of_week' => [1, 2, 3, 4, 5],
        'is_flexible' => true,
        'effective_from' => now()->toDateString(),
        'effective_to' => now()->addMonths(3)->toDateString(),
        'notes' => 'Trial period shift',
    ]);

    $response->assertCreated();
    $shift = ShiftSchedule::findOrFail($response->json('id'));
    expect($shift->is_flexible)->toBeTrue();
    expect($shift->effective_to)->not->toBeNull();
    expect($shift->notes)->toBe('Trial period shift');
});
