<?php

declare(strict_types=1);

use Modules\HR\Models\SalaryBand;


it('can create a salary band', function () {
    actingAsUser('hr-manager');

    $this->postJson('/api/v1/hr/salary-bands', [
        'title' => 'Senior Engineer',
        'level' => 'L5',
        'min_salary' => 60000,
        'mid_salary' => 75000,
        'max_salary' => 90000,
        'currency' => 'EUR',
    ])
        ->assertCreated()
        ->assertJsonPath('title', 'Senior Engineer')
        ->assertJsonPath('currency', 'EUR');
});

it('can list salary bands', function () {
    actingAsUser('hr-manager');
    SalaryBand::factory()->count(4)->create();

    $this->getJson('/api/v1/hr/salary-bands')
        ->assertOk()
        ->assertJsonCount(4);
});

it('can simulate a raise on a salary band', function () {
    actingAsUser('hr-manager');
    $band = SalaryBand::factory()->create([
        'min_salary' => '50000.00',
        'mid_salary' => '60000.00',
        'max_salary' => '75000.00',
    ]);

    $response = $this->postJson("/api/v1/hr/salary-bands/{$band->id}/simulate-raise", [
        'raise_pct' => 10,
    ])
        ->assertOk();

    expect($response->json('simulated.min_salary'))->toBe(55000.0);
    expect($response->json('simulated.max_salary'))->toBe(82500.0);
});
