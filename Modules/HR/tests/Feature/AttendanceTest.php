<?php

declare(strict_types=1);

use App\Models\User;
use Modules\HR\Models\Employee;


beforeEach(function () {
    $this->user = User::factory()->create();
    $this->token = $this->user->createToken('test')->plainTextToken;
    $this->employee = Employee::factory()->create(['user_id' => $this->user->id]);
});

it('employee can clock in', function () {
    $this->withToken($this->token)
        ->postJson('/api/v1/hr/attendance/clock-in')
        ->assertCreated()
        ->assertJsonStructure(['id', 'clock_in', 'employee_id']);
});

it('employee can clock out', function () {
    // Clock in first
    $this->withToken($this->token)->postJson('/api/v1/hr/attendance/clock-in');

    $this->withToken($this->token)
        ->postJson('/api/v1/hr/attendance/clock-out')
        ->assertOk()
        ->assertJsonStructure(['id', 'clock_in', 'clock_out']);
});

it('employee can get attendance status', function () {
    $this->withToken($this->token)
        ->getJson('/api/v1/hr/attendance/status')
        ->assertOk()
        ->assertJsonStructure(['clocked_in']);
});

it('can get employee attendance records', function () {
    $this->withToken($this->token)
        ->getJson("/api/v1/hr/employees/{$this->employee->id}/attendance")
        ->assertOk()
        ->assertJsonStructure(['records', 'monthly_hours']);
});

it('employee can view own attendance', function () {
    $this->withToken($this->token)
        ->getJson('/api/v1/hr/me/attendance')
        ->assertOk()
        ->assertJsonStructure(['records', 'monthly_hours']);
});

it('returns 401 for unauthenticated clock in', function () {
    $this->postJson('/api/v1/hr/attendance/clock-in')->assertUnauthorized();
});
