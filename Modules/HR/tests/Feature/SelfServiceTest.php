<?php

declare(strict_types=1);

use App\Models\User;
use Modules\HR\Models\Employee;
use Modules\Payroll\Models\Payslip;


beforeEach(function () {
    $this->user = User::factory()->create();
    $this->token = $this->user->createToken('test')->plainTextToken;
    $this->employee = Employee::factory()->create(['user_id' => $this->user->id]);
});

it('employee can view own profile', function () {
    $this->withToken($this->token)
        ->getJson('/api/v1/hr/me')
        ->assertOk()
        ->assertJsonPath('id', $this->employee->id);
});

it('employee can update own profile', function () {
    $this->withToken($this->token)
        ->putJson('/api/v1/hr/me', ['phone' => '+33123456789'])
        ->assertOk();

    $this->assertDatabaseHas('hr_employees', [
        'id' => $this->employee->id,
        'phone' => '+33123456789',
    ]);
});

it('employee me endpoint returns own employee only', function () {
    // Verify the main employee endpoint requires proper auth and returns own employee
    $response = $this->withToken($this->token)
        ->getJson('/api/v1/hr/me')
        ->assertOk();

    expect($response->json('id'))->toBe($this->employee->id);
    expect($response->json('user_id'))->toBe($this->user->id);
});

it('employee can list own payslips', function () {
    Payslip::create([
        'employee_id' => $this->employee->id,
        'employee_name' => $this->employee->first_name.' '.$this->employee->last_name,
        'period' => now()->startOfMonth(),
        'gross_salary' => 500000,
        'total_deductions' => 50000,
        'net_salary' => 450000,
        'currency' => 'MGA',
        'status' => 'paid',
    ]);
    Payslip::create([
        'employee_id' => $this->employee->id,
        'employee_name' => $this->employee->first_name.' '.$this->employee->last_name,
        'period' => now()->subMonth()->startOfMonth(),
        'gross_salary' => 500000,
        'total_deductions' => 50000,
        'net_salary' => 450000,
        'currency' => 'MGA',
        'status' => 'paid',
    ]);

    $this->withToken($this->token)
        ->getJson('/api/v1/hr/me/payslips')
        ->assertOk()
        ->assertJsonStructure(['data']);
});

it('employee can view leave balance', function () {
    $this->withToken($this->token)
        ->getJson('/api/v1/hr/me/leave-balance')
        ->assertOk()
        ->assertJsonIsArray();
});

it('returns 401 for unauthenticated requests', function () {
    $this->getJson('/api/v1/hr/me')->assertUnauthorized();
});
