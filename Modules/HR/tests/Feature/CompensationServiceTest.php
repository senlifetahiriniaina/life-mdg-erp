<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\HR\Models\Employee;
use Modules\HR\Models\EmployeeCompensation;
use Modules\HR\Services\CompensationService;

uses(RefreshDatabase::class);

describe('Compensation Service', function () {
    beforeEach(function () {
        $this->service = app(CompensationService::class);
        $this->employee = Employee::factory()->create([
            'hire_date' => now()->subYears(2),
        ]);
    });

    test('getCurrentCompensation returns active compensation record', function () {
        $compensation = EmployeeCompensation::create([
            'employee_id' => $this->employee->id,
            'base_salary' => 100000,
            'bonus_amount' => 20000,
            'benefits_annual_value' => 5000,
            'currency' => 'USD',
            'effective_date' => now()->subMonth(),
        ]);

        $current = $this->service->getCurrentCompensation($this->employee);

        expect($current)->not->toBeNull();
        expect($current->id)->toBe($compensation->id);
    });

    test('calculateTotalCompensation includes all components', function () {
        EmployeeCompensation::create([
            'employee_id' => $this->employee->id,
            'base_salary' => 100000,
            'bonus_amount' => 20000,
            'benefits_annual_value' => 5000,
            'equity_granted' => 10000,
            'effective_date' => now()->subMonth(),
        ]);

        $total = $this->service->calculateTotalCompensation($this->employee);

        expect($total)->toBe(135000.00);
    });

    test('getCompensationBreakdown shows all compensation components', function () {
        EmployeeCompensation::create([
            'employee_id' => $this->employee->id,
            'base_salary' => 100000,
            'bonus_amount' => 20000,
            'benefits_annual_value' => 5000,
            'equity_granted' => 10000,
            'effective_date' => now()->subMonth(),
        ]);

        $breakdown = $this->service->getCompensationBreakdown($this->employee);

        expect($breakdown['base_salary'])->toBe(100000.00);
        expect($breakdown['bonus'])->toBe(20000.00);
        expect($breakdown['benefits'])->toBe(5000.00);
        expect($breakdown['equity'])->toBe(10000.00);
        expect($breakdown['total'])->toBe(135000.00);
    });

    test('createCompensation creates new record and closes previous', function () {
        // Create first compensation
        $old = EmployeeCompensation::create([
            'employee_id' => $this->employee->id,
            'base_salary' => 100000,
            'effective_date' => now()->subYear(),
        ]);

        // Create new compensation
        $new = $this->service->createCompensation($this->employee->id, [
            'base_salary' => 120000,
            'bonus_amount' => 25000,
            'benefits_annual_value' => 6000,
            'currency' => 'USD',
            'effective_date' => now(),
        ]);

        expect((float) $new->base_salary)->toBe(120000.00);
        expect((float) $new->total_compensation)->toBe(151000.00);

        // Old should have end_date set
        $old->refresh();
        expect($old->end_date)->not->toBeNull();
    });

    test('updateEquityVesting calculates vested percentage based on tenure', function () {
        $compensation = EmployeeCompensation::create([
            'employee_id' => $this->employee->id,
            'equity_granted' => 10000,
            'equity_vesting_period_months' => 48,
            'effective_date' => now()->subMonth(),
        ]);

        // Employee has 2 years tenure, vesting period is 4 years
        // So 2/4 = 50% vested
        $this->service->updateEquityVesting($compensation);

        $compensation->refresh();
        expect($compensation->equity_vested_percentage)->toBeCloseTo(50, 10);
    });

    test('calculateBonusAccrual returns correct monthly accrual', function () {
        EmployeeCompensation::create([
            'employee_id' => $this->employee->id,
            'bonus_amount' => 12000,
            'bonus_frequency' => 'annual',
            'effective_date' => now()->subMonth(),
        ]);

        $monthly = $this->service->calculateBonusAccrual($this->employee, 'month');

        expect($monthly)->toBe(1000.00);
    });

    test('compareToMarketBenchmark calculates gap to median', function () {
        EmployeeCompensation::create([
            'employee_id' => $this->employee->id,
            'base_salary' => 100000,
            'bonus_amount' => 10000,
            'effective_date' => now()->subMonth(),
        ]);

        $comparison = $this->service->compareToMarketBenchmark($this->employee, 120000);

        expect($comparison['below_market'])->toBeTrue();
        expect($comparison['difference'])->toBe(-10000.00);
        expect($comparison['gap_amount'])->toBeCloseTo(10000, 1);
    });

    test('getCompensationHistory returns all historical records', function () {
        $comp1 = EmployeeCompensation::create([
            'employee_id' => $this->employee->id,
            'base_salary' => 80000,
            'effective_date' => now()->subYear(),
        ]);

        $comp2 = EmployeeCompensation::create([
            'employee_id' => $this->employee->id,
            'base_salary' => 100000,
            'effective_date' => now()->subMonth(),
        ]);

        $history = $this->service->getCompensationHistory($this->employee);

        expect($history)->toHaveCount(2);
        expect((float) $history[0]->base_salary)->toBe(100000.00); // Most recent first
        expect((float) $history[1]->base_salary)->toBe(80000.00);
    });

    test('generateOfferLetterData includes all compensation details', function () {
        EmployeeCompensation::create([
            'employee_id' => $this->employee->id,
            'base_salary' => 100000,
            'bonus_amount' => 20000,
            'bonus_frequency' => 'annual',
            'benefits_annual_value' => 5000,
            'equity_granted' => 10000,
            'equity_vesting_period_months' => 48,
            'currency' => 'USD',
            'effective_date' => now()->subMonth(),
        ]);

        $offerLetter = $this->service->generateOfferLetterData($this->employee);

        expect($offerLetter['compensation']['base_salary'])->toBe(100000.00);
        expect($offerLetter['compensation']['bonus']['amount'])->toBe(20000.00);
        expect($offerLetter['compensation']['benefits']['annual_value'])->toBe(5000.00);
        expect($offerLetter['compensation']['total_package'])->toBe(135000.00);
    });

    test('auditCompensationRecords finds employees without compensation', function () {
        Employee::factory()->create(['status' => 'active']);

        $issues = $this->service->auditCompensationRecords();

        $noComp = collect($issues)->first(fn ($i) => $i['type'] === 'missing_compensation');
        expect($noComp)->not->toBeNull();
        expect($noComp['count'])->toBeGreaterThan(0);
    });
});

describe('Compensation Calculation Edge Cases', function () {
    beforeEach(function () {
        $this->service = app(CompensationService::class);
        $this->employee = Employee::factory()->create();
    });

    test('returns zero for employee without compensation', function () {
        $total = $this->service->calculateTotalCompensation($this->employee);

        expect($total)->toBe(0.0);
    });

    test('calculateBonusAccrual handles quarterly frequency', function () {
        EmployeeCompensation::create([
            'employee_id' => $this->employee->id,
            'bonus_amount' => 12000,
            'bonus_frequency' => 'quarterly',
            'effective_date' => now()->subMonth(),
        ]);

        $monthly = $this->service->calculateBonusAccrual($this->employee, 'month');

        expect($monthly)->toBe(1000.00); // 12000 / 3 / 4
    });

    test('calculated vested equity reflects tenure', function () {
        $newEmployee = Employee::factory()->create([
            'hire_date' => now(),
        ]);

        $compensation = EmployeeCompensation::create([
            'employee_id' => $newEmployee->id,
            'equity_granted' => 10000,
            'equity_vesting_period_months' => 48,
            'effective_date' => now(),
        ]);

        $this->service->updateEquityVesting($compensation);

        $compensation->refresh();
        expect($compensation->equity_vested_percentage)->toBeLessThan(5); // Very little vested
    });
});
