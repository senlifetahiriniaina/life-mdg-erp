<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Carbon;
use Modules\Accounting\Services\FinancialReportService;

/**
 * @group Accounting - Financial Ratios
 *
 * Liquidity, profitability, efficiency, leverage, market and DuPont financial ratios.
 */
class FinancialRatiosController extends Controller
{
    public function __construct(private FinancialReportService $service) {}

    private function periodFromRequest(Request $request): array
    {
        $from = Carbon::parse($request->query('from', now()->startOfYear()->toDateString()));
        $to   = Carbon::parse($request->query('to',   now()->toDateString()));

        return [$from, $to];
    }

    /** GET /ratios — All ratio categories combined. */
    public function allRatios(Request $request): JsonResponse
    {
        [$from, $to] = $this->periodFromRequest($request);
        $balanceSheet    = $this->service->balanceSheet($to);
        $incomeStatement = $this->service->incomeStatement($from, $to);

        return response()->json([
            'data' => [
                'period'         => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
                'balance_sheet'  => $balanceSheet,
                'income_statement' => $incomeStatement,
            ],
        ]);
    }

    /** GET /ratios/liquidity */
    public function liquidityRatios(Request $request): JsonResponse
    {
        [$from, $to] = $this->periodFromRequest($request);
        $bs = $this->service->balanceSheet($to);

        return response()->json(['data' => $bs, 'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()]]);
    }

    /** GET /ratios/profitability */
    public function profitabilityRatios(Request $request): JsonResponse
    {
        [$from, $to] = $this->periodFromRequest($request);
        $is = $this->service->incomeStatement($from, $to);

        return response()->json(['data' => $is, 'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()]]);
    }

    /** GET /ratios/efficiency */
    public function efficiencyRatios(Request $request): JsonResponse
    {
        [$from, $to] = $this->periodFromRequest($request);
        $is = $this->service->incomeStatement($from, $to);

        return response()->json(['data' => $is, 'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()]]);
    }

    /** GET /ratios/leverage */
    public function leverageRatios(Request $request): JsonResponse
    {
        [$from, $to] = $this->periodFromRequest($request);
        $bs = $this->service->balanceSheet($to);

        return response()->json(['data' => $bs, 'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()]]);
    }

    /** GET /ratios/market */
    public function marketRatios(Request $request): JsonResponse
    {
        [$from, $to] = $this->periodFromRequest($request);

        return response()->json([
            'data'   => ['note' => 'Market ratios require external market data integration.'],
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
        ]);
    }

    /** GET /ratios/dupont */
    public function duPontAnalysis(Request $request): JsonResponse
    {
        [$from, $to] = $this->periodFromRequest($request);
        $bs = $this->service->balanceSheet($to);
        $is = $this->service->incomeStatement($from, $to);

        return response()->json([
            'data'   => ['balance_sheet' => $bs, 'income_statement' => $is],
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
        ]);
    }
}
