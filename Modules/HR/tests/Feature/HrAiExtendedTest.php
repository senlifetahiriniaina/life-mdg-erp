<?php

declare(strict_types=1);
use Modules\HR\Services\AI\HrAIService;


test('can optimize leave planning', function () {
    actingAsUser('hr-manager');

    $mock = Mockery::mock(HrAIService::class);
    $mock->shouldReceive('optimizeLeavePlanning')
        ->once()
        ->andReturn(['plan' => 'Optimized plan...', 'period' => '2026-Q3']);
    app()->instance(HrAIService::class, $mock);

    $this->postJson('/api/v1/hr/ai/optimize-leave-planning', [
        'team_data' => [['employee_id' => 1, 'requested_dates' => ['2026-08-01', '2026-08-15']]],
        'period' => '2026-Q3',
    ])->assertOk()->assertJsonStructure(['plan', 'period']);
});

test('can analyze payslip', function () {
    actingAsUser('hr-manager');

    $mock = Mockery::mock(HrAIService::class);
    $mock->shouldReceive('analyzePayslip')
        ->once()
        ->andReturn(['analysis' => 'No anomalies.', 'employee_id' => 1]);
    app()->instance(HrAIService::class, $mock);

    $this->postJson('/api/v1/hr/ai/analyze-payslip', [
        'employee_id' => 1,
        'payslip_data' => ['gross' => 5000, 'net' => 3800, 'deductions' => 1200],
    ])->assertOk()->assertJsonStructure(['analysis', 'employee_id']);
});

test('can detect payroll anomalies', function () {
    actingAsUser('hr-manager');

    $mock = Mockery::mock(HrAIService::class);
    $mock->shouldReceive('detectPayrollAnomalies')
        ->once()
        ->andReturn(['report' => 'One anomaly detected.']);
    app()->instance(HrAIService::class, $mock);

    $this->postJson('/api/v1/hr/ai/detect-payroll-anomalies', [
        'payroll_data' => [['employee_id' => 1, 'gross' => 5000], ['employee_id' => 2, 'gross' => 50000]],
    ])->assertOk()->assertJsonStructure(['report']);
});
