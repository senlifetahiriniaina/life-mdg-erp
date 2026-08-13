<?php

declare(strict_types=1);

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\HR\Models\Employee;
use Modules\HR\Models\LeaveBalance;
use Modules\HR\Models\LeaveRequest;
use Modules\HR\Services\AbsenceManagementService;

uses(RefreshDatabase::class);

describe('Absence Management Service', function () {
    beforeEach(function () {
        $this->service = app(AbsenceManagementService::class);
        $this->employee = Employee::factory()->create([
            'hire_date' => now()->subYears(3),
        ]);
        $this->manager = Employee::factory()->create();
        $this->employee->update(['manager_id' => $this->manager->id]);
    });

    test('initializeLeaveBalances creates records for all leave types', function () {
        $this->service->initializeLeaveBalances($this->employee);

        $balances = LeaveBalance::where('employee_id', $this->employee->id)->get();

        expect($balances)->toHaveCount(7); // 7 leave types
        expect($balances->pluck('leave_type'))->toContain('vacation', 'sick', 'unpaid');
    });

    test('initializeLeaveBalances sets correct accrual days', function () {
        $this->service->initializeLeaveBalances($this->employee);

        $vacation = LeaveBalance::where('employee_id', $this->employee->id)
            ->where('leave_type', 'vacation')
            ->first();

        expect($vacation->accrual_days_per_year)->toBe(20.0);
        expect($vacation->balance)->toBe(20.0);
    });

    test('submitLeaveRequest creates pending request with correct day count', function () {
        $this->service->initializeLeaveBalances($this->employee);

        $request = $this->service->submitLeaveRequest($this->employee, [
            'start_date' => '2026-05-19', // Monday
            'end_date' => '2026-05-23',   // Friday (5 business days)
            'leave_type' => 'vacation',
            'reason' => 'Vacation',
        ]);

        expect($request->status)->toBe('pending');
        expect($request->days_requested)->toBe(5);
    });

    test('submitLeaveRequest reserves balance as pending', function () {
        $this->service->initializeLeaveBalances($this->employee);

        $this->service->submitLeaveRequest($this->employee, [
            'start_date' => '2026-05-19',
            'end_date' => '2026-05-23',
            'leave_type' => 'vacation',
        ]);

        $balance = $this->service->getLeaveBalance($this->employee, 'vacation');

        expect($balance->pending)->toBe(5.0);
        expect($balance->balance)->toBe(20.0); // Not deducted yet
    });

    test('approveLeaveRequest deducts from balance', function () {
        $this->service->initializeLeaveBalances($this->employee);

        $request = $this->service->submitLeaveRequest($this->employee, [
            'start_date' => '2026-05-19',
            'end_date' => '2026-05-23',
            'leave_type' => 'vacation',
        ]);

        $this->service->approveLeaveRequest($request, $this->manager->id, 'Approved');

        $balance = $this->service->getLeaveBalance($this->employee, 'vacation');

        expect($balance->balance)->toBe(15.0); // 20 - 5
        expect($balance->pending)->toBe(0.0);
        expect($balance->used)->toBe(5.0);
    });

    test('rejectLeaveRequest removes pending balance', function () {
        $this->service->initializeLeaveBalances($this->employee);

        $request = $this->service->submitLeaveRequest($this->employee, [
            'start_date' => '2026-05-19',
            'end_date' => '2026-05-23',
            'leave_type' => 'vacation',
        ]);

        $this->service->rejectLeaveRequest($request, $this->manager->id, 'Not approved');

        $balance = $this->service->getLeaveBalance($this->employee, 'vacation');

        expect($balance->pending)->toBe(0.0);
        expect($balance->balance)->toBe(20.0); // Back to original
    });

    test('getLeaveRequests filters by employee and status', function () {
        $this->service->initializeLeaveBalances($this->employee);

        $req1 = $this->service->submitLeaveRequest($this->employee, [
            'start_date' => '2026-05-19',
            'end_date' => '2026-05-23',
            'leave_type' => 'vacation',
        ]);

        $pending = $this->service->getLeaveRequests($this->employee, 'pending');

        expect($pending)->toHaveCount(1);
        expect($pending[0]->id)->toBe($req1->id);
    });

    test('getPendingLeaveRequests filters by manager', function () {
        $this->service->initializeLeaveBalances($this->employee);

        $request = $this->service->submitLeaveRequest($this->employee, [
            'start_date' => '2026-05-19',
            'end_date' => '2026-05-23',
            'leave_type' => 'vacation',
        ]);

        $pending = $this->service->getPendingLeaveRequests($this->manager->id);

        expect($pending)->toHaveCount(1);
        expect($pending[0]->id)->toBe($request->id);
    });

    test('getLeaveBalanceReport shows all balances for employee', function () {
        $this->service->initializeLeaveBalances($this->employee);

        $report = $this->service->getLeaveBalanceReport($this->employee);

        expect($report)->toHaveKeys(['vacation', 'sick', 'unpaid']);
        expect($report['vacation']['accrual'])->toBe(20.0);
        expect($report['vacation']['available'])->toBe(20.0);
    });

    test('detectAbsenceIssues finds overlapping requests', function () {
        $this->service->initializeLeaveBalances($this->employee);

        $req1 = $this->service->submitLeaveRequest($this->employee, [
            'start_date' => '2026-05-19',
            'end_date' => '2026-05-23',
            'leave_type' => 'vacation',
        ]);

        $this->service->approveLeaveRequest($req1, $this->manager->id);

        $req2 = $this->service->submitLeaveRequest($this->employee, [
            'start_date' => '2026-05-22', // Overlaps
            'end_date' => '2026-05-26',
            'leave_type' => 'vacation',
        ]);

        $issues = $this->service->detectAbsenceIssues($req2);

        $overlapping = collect($issues)->first(fn ($i) => $i['type'] === 'overlapping_request');
        expect($overlapping)->not->toBeNull();
    });

    test('detectAbsenceIssues finds insufficient balance', function () {
        $this->service->initializeLeaveBalances($this->employee);

        // Request more days than available
        $request = LeaveRequest::create([
            'employee_id' => $this->employee->id,
            'leave_type' => 'vacation',
            'start_date' => '2026-05-19',
            'end_date' => '2026-06-05', // Many days
            'days_requested' => 30, // More than 20 available
            'status' => 'pending',
        ]);

        $issues = $this->service->detectAbsenceIssues($request);

        $insufficient = collect($issues)->first(fn ($i) => $i['type'] === 'insufficient_balance');
        expect($insufficient)->not->toBeNull();
    });

    test('accrueLeaveForEmployee adds monthly accrual', function () {
        $this->service->initializeLeaveBalances($this->employee);

        $accrued = $this->service->accrueLeaveForEmployee($this->employee, 'vacation');

        expect($accrued)->toBe(1);

        $balance = $this->service->getLeaveBalance($this->employee, 'vacation');
        $expectedAccrual = 20.0 / 12; // Monthly
        expect($balance->balance)->toBeCloseTo(20.0 + $expectedAccrual, 0.1);
    });

    test('unpaid leave does not require balance', function () {
        $this->service->initializeLeaveBalances($this->employee);

        $request = $this->service->submitLeaveRequest($this->employee, [
            'start_date' => '2026-05-19',
            'end_date' => '2026-05-30',
            'leave_type' => 'unpaid',
        ]);

        $this->service->approveLeaveRequest($request, $this->manager->id);

        // Should not affect balance
        $balance = $this->service->getLeaveBalance($this->employee, 'unpaid');
        expect($balance->balance)->toBe(0.0); // No balance for unpaid
    });
});

describe('Leave Request Business Logic', function () {
    beforeEach(function () {
        $this->service = app(AbsenceManagementService::class);
        $this->employee = Employee::factory()->create([
            'hire_date' => now()->subYears(2),
        ]);
        $this->manager = Employee::factory()->create();
        $this->employee->update(['manager_id' => $this->manager->id]);
        $this->service->initializeLeaveBalances($this->employee);
    });

    test('excludes weekends from business day calculation', function () {
        $request = $this->service->submitLeaveRequest($this->employee, [
            'start_date' => '2026-05-23', // Saturday
            'end_date' => '2026-05-25', // Monday (only 1 business day)
            'leave_type' => 'vacation',
        ]);

        expect($request->days_requested)->toBe(1);
    });

    test('FMLA eligibility requires 12 months employment', function () {
        $newEmployee = Employee::factory()->create([
            'hire_date' => now()->subMonths(6),
        ]);

        $request = LeaveRequest::create([
            'employee_id' => $newEmployee->id,
            'leave_type' => 'maternity',
            'start_date' => now(),
            'end_date' => now()->addDays(60),
            'days_requested' => 60,
            'status' => 'pending',
        ]);

        $issues = $this->service->detectAbsenceIssues($request);

        $fmla = collect($issues)->first(fn ($i) => $i['type'] === 'fmla_ineligible');
        expect($fmla)->not->toBeNull();
    });

    test('deduct operations maintain data integrity', function () {
        $balance = $this->service->getLeaveBalance($this->employee, 'vacation');

        $balance->deductBalance(5);
        $balance->refresh();

        expect($balance->balance)->toBe(15.0);
        expect($balance->used)->toBe(5.0);

        // Can't go negative
        $balance->deductBalance(20);
        $balance->refresh();

        expect($balance->balance)->toBe(0.0);
    });

    test('pending operations reserve balance correctly', function () {
        $balance = $this->service->getLeaveBalance($this->employee, 'vacation');

        $balance->addPending(5);
        $balance->refresh();

        expect($balance->getAvailableBalance())->toBe(15.0); // 20 - 5 pending
    });
});
