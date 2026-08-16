<?php

declare(strict_types=1);

use App\Models\User;
use Modules\Accounting\Models\ExpenseReport;


beforeEach(function () {
    if (\Spatie\Permission\Models\Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }
    $this->user = User::factory()->create();
    $this->user->assignRole('accountant');
    $this->token = $this->user->createToken('test')->plainTextToken;
});

it('can list expense reports', function () {
    ExpenseReport::factory()->count(2)->create();

    $this->withToken($this->token)
        ->getJson('/api/v1/accounting/expense-reports')
        ->assertOk()
        ->assertJsonPath('total', 2);
});

it('can create an expense report', function () {
    $this->withToken($this->token)
        ->postJson('/api/v1/accounting/expense-reports', [
            'employee_id' => $this->user->id,
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
        ])
        ->assertCreated()
        ->assertJsonPath('status', 'draft');
});

it('can add a line to an expense report', function () {
    $report = ExpenseReport::factory()->draft()->create();

    $this->withToken($this->token)
        ->postJson("/api/v1/accounting/expenses/{$report->id}/lines", [
            'date' => '2026-05-07',
            'category' => 'Repas',
            'amount' => 38.50,
        ])
        ->assertCreated()
        ->assertJsonPath('category', 'Repas');
});

it('can add mileage to an expense report', function () {
    $report = ExpenseReport::factory()->draft()->create();

    $this->withToken($this->token)
        ->postJson("/api/v1/accounting/expenses/{$report->id}/mileage", [
            'km' => 120.0,
            'rate' => 0.28,
        ])
        ->assertCreated()
        ->assertJsonPath('category', 'Transport');
});

it('can submit an expense report', function () {
    $report = ExpenseReport::factory()->draft()->create();

    $this->withToken($this->token)
        ->postJson("/api/v1/accounting/expenses/{$report->id}/submit")
        ->assertOk()
        ->assertJsonPath('status', 'submitted');
});
