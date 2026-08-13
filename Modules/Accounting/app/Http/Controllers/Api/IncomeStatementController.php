<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Modules\Accounting\Services\FinancialReportService;

/**
 * @group Accounting - Income Statement
 */
class IncomeStatementController extends Controller
{
    public function __construct(private readonly FinancialReportService $service) {}

    /**
     * Get the income statement (compte de résultat) for a period.
     *
     * @queryParam from date Start date. Example: 2026-01-01
     * @queryParam to date End date. Example: 2026-12-31
     * @queryParam compare_from date Optional comparison period start. Example: 2025-01-01
     * @queryParam compare_to date Optional comparison period end. Example: 2025-12-31
     */
    public function __invoke(Request $request): JsonResponse
    {
        $this->authorize('viewIncomeStatement', auth()->user());

        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'compare_from' => ['nullable', 'date'],
            'compare_to' => ['nullable', 'date', 'after_or_equal:compare_from'],
        ]);

        $from = $request->from ? Carbon::parse($request->from) : Carbon::today()->startOfYear();
        $to = $request->to ? Carbon::parse($request->to) : Carbon::today();

        $compareFrom = $request->compare_from ? Carbon::parse($request->compare_from) : null;
        $compareTo = $request->compare_to ? Carbon::parse($request->compare_to) : null;

        return response()->json(
            $this->service->incomeStatement($from, $to, $compareFrom, $compareTo)
        );
    }
}
