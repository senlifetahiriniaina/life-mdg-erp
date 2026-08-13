<?php

declare(strict_types=1);

namespace Modules\Accounting\Services\AI;

use Modules\Core\Services\AI\AIService;

class TransactionCategorizationService
{
    public function __construct(private readonly AIService $ai) {}

    public function categorizeTransaction(array $transaction): array
    {
        $prompt = 'Categorize this bank transaction into a GL account. Return JSON: {account_code: string, account_name: string, confidence: float 0-1, reason: string}';
        $result = $this->ai->ask($prompt, $transaction, 'Accounting');

        return ['transaction' => $transaction, 'categorization' => $result];
    }
}
