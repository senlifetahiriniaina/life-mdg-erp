<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Modules\Accounting\Services\FinancialReportService;

/**
 * @group Accounting - Balance Sheet
 */
class BalanceSheetController extends Controller
{
    public function __construct(private readonly FinancialReportService $service) {}

    /**
     * Get the balance sheet (bilan comptable) at a given date.
     *
     * @queryParam as_of date Reference date (default: today). Example: 2026-12-31
     * @queryParam compare_as_of date Optional comparison date for N-1 column. Example: 2025-12-31
     */
    public function __invoke(Request $request): JsonResponse
    {
        $this->authorize('viewBalanceSheet', auth()->user());

        $request->validate([
            'as_of' => ['nullable', 'date'],
            'compare_as_of' => ['nullable', 'date'],
        ]);

        $asOf = $request->as_of ? Carbon::parse($request->as_of) : Carbon::today();
        $compareAsOf = $request->compare_as_of ? Carbon::parse($request->compare_as_of) : null;

        return response()->json($this->service->balanceSheet($asOf, $compareAsOf));
    }
}
