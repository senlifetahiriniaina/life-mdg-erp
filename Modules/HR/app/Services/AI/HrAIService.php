<?php

declare(strict_types=1);

namespace Modules\HR\Services\AI;

use Modules\Core\Services\AI\AIService;

class HrAIService
{
    public function __construct(private readonly AIService $ai) {}

    public function optimizeLeavePlanning(array $teamData, string $period): array
    {
        $prompt = 'Analyze team leave requests and workload to suggest optimal leave scheduling. Return JSON with: approved_leaves (array), conflicts (array), coverage_gaps (array), recommendations (array).';
        $result = $this->ai->ask($prompt, ['team' => json_encode($teamData), 'period' => $period], 'HR', 'en');

        return ['plan' => $result, 'period' => $period];
    }

    public function analyzePayslip(int $employeeId, array $payslipData): array
    {
        $prompt = 'Analyze this payslip for anomalies, trends, and insights. Return JSON with: anomalies (array), year_on_year_change (float), tax_optimization_tips (array), summary (string).';
        $result = $this->ai->ask($prompt, ['payslip' => json_encode($payslipData)], 'HR', 'en');

        return ['analysis' => $result, 'employee_id' => $employeeId];
    }

    public function detectPayrollAnomalies(array $payrollData): array
    {
        $prompt = 'Detect anomalies and inconsistencies in this payroll data. Return JSON with: anomalies (array of {employee_id, type, description, severity}), total_anomalies (int), recommended_actions (array).';
        $result = $this->ai->ask($prompt, ['payroll' => json_encode($payrollData)], 'HR', 'en');

        return ['report' => $result];
    }
}
