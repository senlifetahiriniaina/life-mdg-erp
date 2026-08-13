<?php

declare(strict_types=1);

namespace Modules\Accounting\Services\AI;

use Illuminate\Support\Facades\DB;
use Modules\Core\Services\AI\AIService;

class BalanceSheetSummaryService
{
    public function __construct(private readonly AIService $ai) {}

    public function generateSummary(string $month = '', string $locale = 'fr'): string
    {
        $balanceData = $this->getBalanceSheetData($month);

        $prompt = $locale === 'fr'
            ? 'Résumez ce bilan en langage naturel pour un comptable non-spécialiste. Incluez: actif, passif, différence, tendances clés, alertes. Soyez concis en 3-4 phrases.'
            : 'Summarize this balance sheet in plain English for a non-accountant. Include: assets, liabilities, differences, key trends, alerts. Be concise in 3-4 sentences.';

        return $this->ai->ask($prompt, $balanceData, 'Accounting', $locale);
    }

    private function getBalanceSheetData(string $month = ''): array
    {
        try {
            $dateFilter = $month ? " AND MONTH(created_at) = MONTH(STR_TO_DATE('{$month}', '%Y-%m'))" : '';

            $assets = (float) DB::table('acc_journal_entries as je')
                ->join('acc_journal_entry_lines as jel', 'je.id', 'jel.journal_entry_id')
                ->join('acc_chart_of_accounts as coa', 'jel.account_id', 'coa.id')
                ->where('coa.type', 'asset')
                ->whereRaw("1=1{$dateFilter}")
                ->sum('jel.debit_amount');

            $liabilities = (float) DB::table('acc_journal_entries as je')
                ->join('acc_journal_entry_lines as jel', 'je.id', 'jel.journal_entry_id')
                ->join('acc_chart_of_accounts as coa', 'jel.account_id', 'coa.id')
                ->where('coa.type', 'liability')
                ->whereRaw("1=1{$dateFilter}")
                ->sum('jel.credit_amount');

            $equity = (float) DB::table('acc_journal_entries as je')
                ->join('acc_journal_entry_lines as jel', 'je.id', 'jel.journal_entry_id')
                ->join('acc_chart_of_accounts as coa', 'jel.account_id', 'coa.id')
                ->where('coa.type', 'equity')
                ->whereRaw("1=1{$dateFilter}")
                ->sum('jel.credit_amount');

            return [
                'period' => $month ?: date('Y-m'),
                'assets' => $assets,
                'liabilities' => $liabilities,
                'equity' => $equity,
                'balance_check' => $assets == ($liabilities + $equity),
            ];
        } catch (\Throwable) {
            return ['error' => 'Unable to fetch balance sheet data'];
        }
    }
}
