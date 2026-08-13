<?php

declare(strict_types=1);

namespace Modules\HR\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\HR\Services\AI\HrAIService;

/**
 * @group HR - HrAI
 *
 * AI-powered HR analytics and recommendations.
 */
class HrAIController extends Controller
{
    public function __construct(private readonly HrAIService $ai) {}

    public function optimizeLeavePlanning(Request $request): JsonResponse
    {
        $data = $request->validate([
            'team_data' => 'required|array',
            'period' => 'required|string',
        ]);

        return response()->json($this->ai->optimizeLeavePlanning($data['team_data'], $data['period']));
    }

    public function analyzePayslip(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => 'required|integer',
            'payslip_data' => 'required|array',
        ]);

        return response()->json($this->ai->analyzePayslip($data['employee_id'], $data['payslip_data']));
    }

    public function detectPayrollAnomalies(Request $request): JsonResponse
    {
        $data = $request->validate(['payroll_data' => 'required|array']);

        return response()->json($this->ai->detectPayrollAnomalies($data['payroll_data']));
    }
}
