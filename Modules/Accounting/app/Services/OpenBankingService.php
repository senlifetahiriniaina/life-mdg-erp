<?php

declare(strict_types=1);

namespace Modules\Accounting\Services;

use App\Models\User;
use Modules\Accounting\Models\BankAccount;
use Modules\Accounting\Models\OpenBankingConnection;
use Modules\Accounting\Models\OpenBankingSyncLog;

class OpenBankingService
{
    private const NORDIGEN_BASE = 'https://ob.nordigen.com/api/v2';

    public function __construct(
        private readonly BankReconciliationService $reconciliationService,
    ) {}

    /**
     * Initiate an OAuth connection to a bank via a provider.
     * Returns the bank consent URL.
     */
    public function initiateConnection(BankAccount $account, string $provider, User $user): string
    {
        // For Nordigen (GoCardless) — mock token exchange
        if ($provider === 'nordigen') {
            $redirectUrl = config('app.url').'/api/v1/accounting/open-banking/callback';

            // In production, exchange client credentials for token here.
            // We store a placeholder and return the consent URL.
            $requisitionId = 'req_'.bin2hex(random_bytes(8));

            OpenBankingConnection::create([
                'bank_account_id' => $account->id,
                'provider' => $provider,
                'access_token' => 'pending_'.$requisitionId,
                'refresh_token' => null,
                'token_expires_at' => null,
                'requisition_id' => $requisitionId,
                'status' => 'pending',
                'created_by' => $user->id,
            ]);

            return self::NORDIGEN_BASE.'/requisitions/'.$requisitionId.'/link/';
        }

        throw new \InvalidArgumentException("Unsupported provider: {$provider}");
    }

    /**
     * Complete OAuth flow after bank consent redirect.
     */
    public function completeConnection(string $requisitionId, string $provider): OpenBankingConnection
    {
        /** @var OpenBankingConnection $connection */
        $connection = OpenBankingConnection::where('requisition_id', $requisitionId)
            ->where('provider', $provider)
            ->firstOrFail();

        $connection->update([
            'status' => 'active',
            'access_token' => 'token_'.bin2hex(random_bytes(16)),
            'token_expires_at' => now()->addDays(90),
        ]);

        $connection->refresh();

        return $connection;
    }

    /**
     * Sync transactions from the provider for a connection.
     */
    public function syncTransactions(OpenBankingConnection $conn): int
    {
        $bankAccount = BankAccount::findOrFail($conn->bank_account_id);

        try {
            // In production, call the real provider API.
            // Here we mock the Nordigen transactions endpoint.
            $transactions = $this->fetchTransactionsFromProvider($conn);

            $count = $this->reconciliationService->importTransactions(
                $bankAccount,
                $transactions
            );

            OpenBankingSyncLog::create([
                'connection_id' => $conn->id,
                'synced_at' => now(),
                'transactions_fetched' => $count,
                'status' => 'success',
                'error_message' => null,
            ]);

            return $count;
        } catch (\Throwable $e) {
            OpenBankingSyncLog::create([
                'connection_id' => $conn->id,
                'synced_at' => now(),
                'transactions_fetched' => 0,
                'status' => 'error',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Revoke an open banking connection.
     */
    public function revokeConnection(OpenBankingConnection $conn): void
    {
        $conn->update(['status' => 'revoked']);
    }

    /**
     * Fetch raw transactions from the provider (mocked).
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchTransactionsFromProvider(OpenBankingConnection $conn): array
    {
        // Production: call provider API using $conn->access_token
        // For now, return empty array (no mock data injected)
        return [];
    }
}
