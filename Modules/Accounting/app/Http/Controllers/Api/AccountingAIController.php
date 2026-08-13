<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Accounting\Services\AI\AccountingAIService;

/**
 * @group Accounting - AI
 *
 * AI-powered accounting analysis and suggestions.
 */
class AccountingAIController extends Controller
{
    public function __construct(private readonly AccountingAIService $ai) {}

    public function detectAnomalies(Request $request): JsonResponse
    {
        $data = $request->validate(['entries' => 'required|array|max:100']);

        return response()->json($this->ai->detectAnomalies($data['entries']));
    }

    public function categorizeExpense(Request $request): JsonResponse
    {
        $data = $request->validate([
            'description' => 'required|string|max:500',
            'vendor' => 'nullable|string|max:255',
        ]);

        return response()->json($this->ai->categorizeExpense($data['description'], $data['vendor'] ?? ''));
    }

    public function financialNarrative(Request $request): JsonResponse
    {
        $data = $request->validate([
            'financial_data' => 'required|array',
            'period' => 'required|string',
        ]);

        return response()->json(['narrative' => $this->ai->generateFinancialNarrative($data['financial_data'], $data['period'])]);
    }

    public function forecastCashFlow(Request $request): JsonResponse
    {
        $data = $request->validate([
            'historical_data' => 'required|array',
            'forecast_days' => 'nullable|integer|min:7|max:365',
        ]);

        return response()->json($this->ai->forecastCashFlow($data['historical_data'], $data['forecast_days'] ?? 90));
    }

    public function summarizeBalanceSheet(Request $request): JsonResponse
    {
        $data = $request->validate([
            'balance_sheet_data' => 'required|array',
            'locale' => 'nullable|string|in:en,fr,es,pt',
        ]);

        return response()->json($this->ai->summarizeBalanceSheet($data['balance_sheet_data'], $data['locale'] ?? 'en'));
    }

    public function categorizeTransactions(Request $request): JsonResponse
    {
        $data = $request->validate(['transactions' => 'required|array|min:1']);

        return response()->json($this->ai->categorizeTransactions($data['transactions']));
    }
}
