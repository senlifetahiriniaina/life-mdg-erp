<?php

declare(strict_types=1);

namespace Modules\Accounting\Services\AI;

use Modules\Core\Services\AI\AIService;

class AccountingAIService
{
    public function __construct(private readonly AIService $ai) {}

    public function detectAnomalies(array $journalEntries): array
    {
        $response = $this->ai->ask(
            'Review these accounting journal entries and flag any anomalies, duplicate transactions, unusual amounts, or policy violations. Return valid JSON only: {"anomalies":[{"entry_ref":"...","severity":"high|medium|low","issue":"...","recommendation":"..."}]}',
            ['entries' => json_encode($journalEntries)],
            'Accounting'
        );
        $decoded = json_decode($response, true);

        return $decoded ?? ['anomalies' => [], 'raw' => $response];
    }

    public function categorizeExpense(string $description, string $vendor = ''): array
    {
        $response = $this->ai->ask(
            'Suggest the most appropriate chart of accounts category and account code for this expense. Return valid JSON only: {"category":"...","account_type":"expense","suggested_code":"...","confidence":"high|medium|low"}',
            ['description' => $description, 'vendor' => $vendor],
            'Accounting'
        );
        $decoded = json_decode($response, true);

        return $decoded ?? ['category' => 'Uncategorized', 'raw' => $response];
    }

    public function generateFinancialNarrative(array $financialData, string $period): string
    {
        return $this->ai->analyzeData(
            array_merge($financialData, ['period' => $period]),
            'accountant'
        );
    }

    public function forecastCashFlow(array $historicalData, int $forecastDays = 90): array
    {
        $prompt = "Forecast cash flow for the next {$forecastDays} days based on historical transactions. Return JSON with: forecast (array of {date, projected_inflow, projected_outflow, net_position}), confidence_score (float), risk_factors (array), recommendations (array).";
        $result = $this->ai->ask($prompt, ['historical_data' => json_encode($historicalData)], 'Accounting', 'en');

        return ['forecast' => $result, 'forecast_days' => $forecastDays];
    }

    public function summarizeBalanceSheet(array $balanceSheetData, string $locale = 'en'): array
    {
        $prompt = 'Write a clear, executive-level narrative summary of this balance sheet in natural language. Return JSON with: summary (string), key_insights (array), health_score (int 0-100), areas_of_concern (array), positive_indicators (array).';
        $result = $this->ai->ask($prompt, ['balance_sheet' => json_encode($balanceSheetData)], 'Accounting', $locale);

        return ['narrative' => $result, 'locale' => $locale];
    }

    public function categorizeTransactions(array $transactions): array
    {
        $prompt = 'Automatically categorize these financial transactions into accounting categories. Return JSON with: categorized (array of {transaction_id, suggested_category, account_code, confidence, reasoning}), uncategorized_count (int).';
        $result = $this->ai->ask($prompt, ['transactions' => json_encode($transactions)], 'Accounting', 'en');

        return ['categorization' => $result, 'total' => count($transactions)];
    }
}
