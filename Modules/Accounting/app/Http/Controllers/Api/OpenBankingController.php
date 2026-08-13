<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\HandleWebhookRequest;
use App\Jobs\ProcessBridgeWebhookJob;
use App\Jobs\ProcessNordigenWebhookJob;
use App\Jobs\ProcessPlaidWebhookJob;
use App\Models\WebhookAuditLog;
use App\Services\WebhookSecurityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Accounting\Models\BankAccount;
use Modules\Accounting\Models\OpenBankingConnection;
use Modules\Accounting\Services\OpenBankingService;

/**
 * @group Accounting
 *
 * Manage OpenBanking resources in Accounting module.
 */
class OpenBankingController extends Controller
{
    public function __construct(
        private readonly OpenBankingService $service,
        private readonly WebhookSecurityService $securityService,
    ) {}

    public function connections(): JsonResponse
    {
        $connections = OpenBankingConnection::with('bankAccount')
            ->where('status', '!=', 'revoked')
            ->latest()
            ->get();

        return response()->json($connections);
    }

    public function connect(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'bank_account_id' => ['required', 'exists:acc_bank_accounts,id'],
            'provider' => ['required', 'in:nordigen,plaid,bridge'],
        ]);

        /** @var BankAccount $account */
        $account = BankAccount::findOrFail($validated['bank_account_id']);

        $url = $this->service->initiateConnection($account, $validated['provider'], $request->user());

        return response()->json(['redirect_url' => $url]);
    }

    public function callback(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'requisition_id' => ['required', 'string'],
            'provider' => ['required', 'string'],
        ]);

        $connection = $this->service->completeConnection(
            $validated['requisition_id'],
            $validated['provider'],
        );

        return response()->json($connection->load('bankAccount'));
    }

    public function sync(OpenBankingConnection $connection): JsonResponse
    {
        if ($connection->status !== 'active') {
            return response()->json(['message' => 'Connection is not active.'], 422);
        }

        $count = $this->service->syncTransactions($connection);

        return response()->json(['synced' => $count]);
    }

    public function revoke(OpenBankingConnection $connection): JsonResponse
    {
        $this->service->revokeConnection($connection);

        return response()->json(null, 204);
    }

    public function handleWebhook(HandleWebhookRequest $request): JsonResponse
    {
        $startTime = microtime(true);
        $validated = $request->validated();
        $provider = $validated['provider'];
        $eventType = $validated['webhook_type'] ?? $validated['event_type'] ?? 'unknown';
        $nonce = $validated['nonce'];
        $ipAddress = $request->ip();

        try {
            // Route to appropriate handler
            match ($provider) {
                'plaid' => $this->handlePlaidWebhook($request),
                'nordigen' => $this->handleNordigenWebhook($request),
                'bridge' => $this->handleBridgeWebhook($request),
                default => $this->securityService->logWebhookError($provider, 'Unknown webhook provider')
            };

            $processingTimeMs = (int) ((microtime(true) - $startTime) * 1000);

            WebhookAuditLog::logWebhookEvent(
                provider: $provider,
                eventType: $eventType,
                status: 'success',
                ipAddress: $ipAddress,
                responseCode: 200,
                requestHeaders: $request->headers->all(),
                processingTimeMs: $processingTimeMs,
                nonce: $nonce
            );

            return response()->json(['status' => 'received'], 200);
        } catch (\Exception $e) {
            $processingTimeMs = (int) ((microtime(true) - $startTime) * 1000);

            WebhookAuditLog::logWebhookEvent(
                provider: $provider,
                eventType: $eventType,
                status: 'failure',
                ipAddress: $ipAddress,
                responseCode: 500,
                errorMessage: $e->getMessage(),
                processingTimeMs: $processingTimeMs,
                nonce: $nonce
            );

            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    private function handlePlaidWebhook(Request $request): void
    {
        $eventType = $request->input('webhook_type');
        $transactionIds = $request->input('added_transaction_ids', []);

        ProcessPlaidWebhookJob::dispatch($eventType, $transactionIds);
    }

    private function handleNordigenWebhook(Request $request): void
    {
        $signature = $request->input('signature');
        $requisitionId = $request->input('requisition_id');

        ProcessNordigenWebhookJob::dispatch($requisitionId, $signature);
    }

    private function handleBridgeWebhook(Request $request): void
    {
        $webhook = $request->input('webhook');
        $clientId = $request->input('client_id');

        ProcessBridgeWebhookJob::dispatch($webhook, $clientId);
    }
}
