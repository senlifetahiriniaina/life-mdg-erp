<?php

declare(strict_types=1);

use App\Models\User;
use Modules\HR\Models\AttendanceException;
use Modules\HR\Models\AttendanceRecord;
use Modules\HR\Models\BiometricDevice;
use Modules\HR\Models\Employee;
use Modules\HR\Models\TimeOffRequest;

/**
 * Chantier 8.3 (HR): AttendanceBiometricController's 13 methods were fully
 * written but had zero routes, no table for 6 of its models, and an
 * unregistered AttendancePolicy. Covers the newly-wired endpoints + the
 * clockIn()/clockOut()/verify() bugs fixed alongside the routing.
 */
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function hrAttendanceBiometricUser(string $role = 'hr-manager'): User
{
    if (\Spatie\Permission\Models\Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

test('registering a biometric device and listing devices works', function () {
    $user = hrAttendanceBiometricUser();

    $response = test()->actingAs($user, 'sanctum')->postJson('/api/v1/hr/biometric-devices', [
        'device_id' => 'DEV-100',
        'device_name' => 'Front Desk',
        'device_type' => 'fingerprint',
        'location' => 'Lobby',
    ]);
    $response->assertCreated();

    test()->actingAs($user, 'sanctum')->getJson('/api/v1/hr/biometric-devices')
        ->assertOk()
        ->assertJsonFragment(['device_id' => 'DEV-100']);
});

test('a role with no hr.* permissions cannot register a biometric device', function () {
    // Not 'employee' — that role deliberately gets every non-delete permission
    // across every module by design (see RolesAndPermissionsSeeder), so
    // manageBiometricDevices (a non-delete ability) would pass for it too.
    // 'sales-rep' only gets crm.* permissions, same precedent used by
    // Chantier83LogisticsRbacTest for an analogous negative check.
    $user = hrAttendanceBiometricUser('sales-rep');

    test()->actingAs($user, 'sanctum')->postJson('/api/v1/hr/biometric-devices', [
        'device_id' => 'DEV-200',
        'device_name' => 'Warehouse Gate',
        'device_type' => 'rfid',
        'location' => 'Warehouse',
    ])->assertForbidden();
});

test('clock-in stores device linkage and lat/lng correctly, then clock-out and verify work', function () {
    $user = hrAttendanceBiometricUser();
    $employee = Employee::factory()->create();
    $device = BiometricDevice::factory()->create();

    $clockIn = test()->actingAs($user, 'sanctum')->postJson('/api/v1/hr/attendance-records/clock-in', [
        'employee_id' => $employee->id,
        'device_id' => $device->id,
        'clock_in_method' => 'biometric',
        'latitude' => 40.7128,
        'longitude' => -74.0060,
    ])->assertCreated();

    $recordId = $clockIn->json('id');
    $record = AttendanceRecord::findOrFail($recordId);
    expect((float) $record->location_lat)->toBe(40.7128);
    expect((float) $record->location_lng)->toBe(-74.006);
    expect($record->device_id)->toBe($device->id);
    expect($record->verification_status)->toBe('pending');

    test()->actingAs($user, 'sanctum')->postJson("/api/v1/hr/attendance-records/{$recordId}/verify")
        ->assertOk()
        ->assertJsonFragment(['verification_status' => 'verified']);

    test()->actingAs($user, 'sanctum')->postJson("/api/v1/hr/attendance-records/{$recordId}/clock-out")
        ->assertOk();
    expect($record->fresh()->clock_out)->not->toBeNull();
});

test('attendance exceptions can be listed and approved', function () {
    $user = hrAttendanceBiometricUser();
    $exception = AttendanceException::factory()->create(['status' => 'flagged']);

    test()->actingAs($user, 'sanctum')->getJson('/api/v1/hr/attendance-exceptions')
        ->assertOk();

    test()->actingAs($user, 'sanctum')->postJson("/api/v1/hr/attendance-exceptions/{$exception->id}/approve")
        ->assertOk()
        ->assertJsonFragment(['status' => 'approved']);
});

test('time-off requests can be created and approved', function () {
    $user = hrAttendanceBiometricUser();
    $employee = Employee::factory()->create(['user_id' => $user->id]);

    $response = test()->actingAs($user, 'sanctum')->postJson('/api/v1/hr/time-off-requests', [
        'request_type' => 'pto',
        'start_date' => now()->addDays(5)->toDateString(),
        'end_date' => now()->addDays(7)->toDateString(),
        'duration_days' => 3,
    ])->assertCreated();

    $requestId = $response->json('id');
    expect(TimeOffRequest::findOrFail($requestId)->employee_id)->toBe($employee->id);

    test()->actingAs($user, 'sanctum')->postJson("/api/v1/hr/time-off-requests/{$requestId}/approve")
        ->assertOk()
        ->assertJsonFragment(['status' => 'approved']);
});

test('shifts can be created and listed', function () {
    $user = hrAttendanceBiometricUser();
    $employee = Employee::factory()->create();

    test()->actingAs($user, 'sanctum')->postJson('/api/v1/hr/shifts', [
        'employee_id' => $employee->id,
        'shift_name' => 'Morning',
        'start_time' => '09:00:00',
        'end_time' => '17:00:00',
        'working_hours' => 8,
        'days_of_week' => [1, 2, 3, 4, 5],
        'effective_from' => now()->toDateString(),
    ])->assertCreated();

    test()->actingAs($user, 'sanctum')->getJson('/api/v1/hr/shifts')
        ->assertOk()
        ->assertJsonFragment(['shift_name' => 'Morning']);
});

test('attendance analytics endpoint is reachable', function () {
    $user = hrAttendanceBiometricUser();

    test()->actingAs($user, 'sanctum')->getJson('/api/v1/hr/attendance-analytics')
        ->assertOk();
});
