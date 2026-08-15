<?php

declare(strict_types=1);

namespace Modules\BI\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

/**
 * @group Controllers - Bi Nl Query
 *
 * Manage Bi Nl Query resources.
 */
class BiNlQueryController extends Controller
{
    public function query(Request $request): JsonResponse
    {
        $request->validate([
            'question' => 'required|string|max:500',
            'context' => 'nullable|string',
        ]);

        $question = $request->input('question');
        $result = $this->interpretQuestion($question);

        return response()->json([
            'question' => $question,
            'sql' => $result['sql'],
            'data' => $result['data'],
            'chart_type' => $result['chart_type'],
        ]);
    }

    /** @return array<string, mixed> */
    private function interpretQuestion(string $q): array
    {
        $lower = strtolower($q);

        if (str_contains($lower, 'ventes') || str_contains($lower, 'chiffre')) {
            return [
                'sql' => 'SELECT month, SUM(amount) FROM invoices GROUP BY month',
                'data' => [
                    ['month' => 'Jan', 'amount' => 45000],
                    ['month' => 'Feb', 'amount' => 52000],
                    ['month' => 'Mar', 'amount' => 48000],
                ],
                'chart_type' => 'bar',
            ];
        }

        if (str_contains($lower, 'ticket') || str_contains($lower, 'support')) {
            return [
                'sql' => 'SELECT status, COUNT(*) FROM tickets GROUP BY status',
                'data' => [
                    ['status' => 'open', 'count' => 12],
                    ['status' => 'closed', 'count' => 45],
                    ['status' => 'pending', 'count' => 8],
                ],
                'chart_type' => 'donut',
            ];
        }

        if (str_contains($lower, 'employ') || str_contains($lower, 'département')) {
            return [
                'sql' => 'SELECT department, COUNT(*) FROM employees GROUP BY department',
                'data' => [
                    ['department' => 'Tech', 'count' => 25],
                    ['department' => 'Sales', 'count' => 18],
                    ['department' => 'HR', 'count' => 7],
                ],
                'chart_type' => 'bar',
            ];
        }

        return [
            'sql' => 'SELECT COUNT(*) total FROM users',
            'data' => [['total' => 150]],
            'chart_type' => 'kpi',
        ];
    }
}
