<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Modules\Accounting\Services\FinancialReportService;

/**
 * @group Accounting - Cash Flow
 */
class CashFlowController extends Controller
{
    public function __construct(private readonly FinancialReportService $service) {}

    /**
     * Get the cash flow statement (flux de trésorerie) for a period.
     *
     * @queryParam from date Start date. Example: 2026-01-01
     * @queryParam to date End date. Example: 2026-12-31
     */
    public function __invoke(Request $request): JsonResponse
    {
        $this->authorize('viewCashFlow', auth()->user());

        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $from = $request->from ? Carbon::parse($request->from) : Carbon::today()->startOfYear();
        $to = $request->to ? Carbon::parse($request->to) : Carbon::today();

        return response()->json($this->service->cashFlow($from, $to));
    }
}
