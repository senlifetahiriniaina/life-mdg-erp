<?php

declare(strict_types=1);

use App\Models\User;
use Modules\HR\Models\Employee;

/**
 * Chantier 8.3 (HR): EmployeeSelfServiceController::me()/updateMe() and
 * EmployeePortalController::profile() returned response()->json($employee) —
 * the raw Eloquent model, with no $hidden array on Employee, so the response
 * included the plain-array bank_details AND the auto-decrypted
 * bank_details_encrypted (Laravel's encrypted:array cast decrypts on
 * serialization) in full, plus raw national_id/passport_number. All three
 * now go through SelfServiceEmployeeResource, which exposes only
 * masked_bank_details (via Employee::getMaskedBankDetailsAttribute()) and
 * omits national_id/passport_number entirely.
 */
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function hrPiiLeakTestEmployee(): array
{
    if (\Spatie\Permission\Models\Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }
    $user = User::factory()->create();
    $user->assignRole('employee');

    $employee = Employee::factory()->create([
        'user_id' => $user->id,
        'national_id' => 'NID-SECRET-12345',
        'passport_number' => 'PASSPORT-SECRET-67890',
        'bank_details' => [
            'account_number' => '1234567890',
            'iban' => 'FR7630006000011234567890189',
            'routing_number' => '30006000',
        ],
    ]);

    return [$user, $employee];
}

test('me endpoint never exposes raw bank details or national id/passport', function () {
    [$user] = hrPiiLeakTestEmployee();

    $response = test()->actingAs($user, 'sanctum')->getJson('/api/v1/hr/me');

    $response->assertOk();
    $json = $response->json();

    expect($json)->not->toHaveKey('bank_details');
    expect($json)->not->toHaveKey('bank_details_encrypted');
    expect($json)->not->toHaveKey('national_id');
    expect($json)->not->toHaveKey('national_id_encrypted');
    expect($json)->not->toHaveKey('passport_number');
    expect($json)->not->toHaveKey('passport_number_encrypted');

    expect($json['masked_bank_details']['account_number'])->not->toBe('1234567890');
    expect($json['masked_bank_details']['account_number'])->toEndWith('7890');
    expect($json['masked_bank_details']['iban'])->not->toBe('FR7630006000011234567890189');
});

test('portal/profile endpoint never exposes raw bank details or national id/passport', function () {
    [$user] = hrPiiLeakTestEmployee();

    $response = test()->actingAs($user, 'sanctum')->getJson('/api/v1/hr/portal/profile');

    $response->assertOk();
    $json = $response->json();

    expect($json)->not->toHaveKey('bank_details');
    expect($json)->not->toHaveKey('bank_details_encrypted');
    expect($json)->not->toHaveKey('national_id');
    expect($json)->not->toHaveKey('passport_number');
});

test('employee-portal endpoint never exposes raw bank details or national id/passport', function () {
    [$user] = hrPiiLeakTestEmployee();

    $response = test()->actingAs($user, 'sanctum')->getJson('/api/v1/hr/employee-portal');

    $response->assertOk();
    $json = $response->json();

    expect($json)->not->toHaveKey('bank_details');
    expect($json)->not->toHaveKey('bank_details_encrypted');
    expect($json)->not->toHaveKey('national_id');
    expect($json)->not->toHaveKey('passport_number');
});

test('updateMe endpoint response never exposes raw bank details after saving an iban', function () {
    [$user] = hrPiiLeakTestEmployee();

    $response = test()->actingAs($user, 'sanctum')->putJson('/api/v1/hr/me', [
        'bank_iban' => 'DE89370400440532013000',
    ]);

    $response->assertOk();
    $json = $response->json();

    expect($json)->not->toHaveKey('bank_details');
    expect($json)->not->toHaveKey('bank_details_encrypted');
    expect($json['masked_bank_details']['iban'] ?? null)->not->toBe('DE89370400440532013000');
});
