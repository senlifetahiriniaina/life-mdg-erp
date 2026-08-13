<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Accounting\Http\Resources\BankFeedTransactionResource;
use Modules\Accounting\Http\Resources\OpenBankingConnectionResource;
use Modules\Accounting\Models\AccBankFeed;
use Modules\Accounting\Models\AccBankFeedTransaction;
use Modules\Accounting\Models\AccOpenBankingConnection;
use Modules\Accounting\Models\OpenBankingConnection;
use Modules\Accounting\Services\AccOpenBankingService;
use Modules\Accounting\Services\OpenBankingService;

/**
 * @group Accounting
 *
 * Manage AccOpenBanking resources in Accounting module.
 */
class AccOpenBankingController extends Controller
{
    public function __construct(
        private readonly AccOpenBankingService $service,
    ) {}

    /**
     * GET /api/v1/accounting/open-banking/connections
     */
    public function indexConnections(): AnonymousResourceCollection
    {
        $connections = AccOpenBankingConnection::withCount('feeds')
            ->orderByDesc('created_at')
            ->get();

        return OpenBankingConnectionResource::collection($connections);
    }

    /**
     * POST /api/v1/accounting/open-banking/connections
     */
    public function storeConnection(Request $request): OpenBankingConnectionResource
    {
        $validated = $request->validate([
            'bank_code' => ['required', 'string', 'max:20'],
            'auth_code' => ['required', 'string'],
        ]);

        $connection = $this->service->connect($validated['bank_code'], $validated['auth_code']);

        return new OpenBankingConnectionResource($connection);
    }

    /**
     * DELETE /api/v1/accounting/open-banking/connections/{connection}
     */
    public function destroyConnection(AccOpenBankingConnection $connection): JsonResponse
    {
        $connection->delete();

        return response()->json(null, 204);
    }

    /**
     * POST /api/v1/accounting/open-banking/connections/{connection}/sync
     */
    public function syncConnection(AccOpenBankingConnection $connection): JsonResponse
    {
        if ($connection->status !== 'active') {
            return response()->json(['message' => 'Connection is not active.'], 422);
        }

        $count = $this->service->sync($connection);

        return response()->json([
            'synced' => $count,
            'synced_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * GET /api/v1/accounting/open-banking/feeds/{connection}
     */
    public function indexFeeds(AccOpenBankingConnection $connection): JsonResponse
    {
        $feeds = AccBankFeed::where('connection_id', $connection->id)
            ->withCount('transactions')
            ->get();

        return response()->json($feeds);
    }

    /**
     * GET /api/v1/accounting/open-banking/transactions
     */
    public function indexTransactions(Request $request): AnonymousResourceCollection
    {
        $query = AccBankFeedTransaction::query()->with('feed');

        if ($request->filled('feed_id')) {
            $query->where('feed_id', $request->integer('feed_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('date', '>=', $request->string('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('date', '<=', $request->string('date_to'));
        }

        $transactions = $query->orderByDesc('date')->paginate(50);

        return BankFeedTransactionResource::collection($transactions);
    }

    public function connect(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'bank_account_id' => 'required|integer',
            'provider' => 'required|string',
        ]);

        $redirectUrl = "https://nordigen.com/oauth?bank_account={$validated['bank_account_id']}&provider={$validated['provider']}";

        return response()->json(['redirect_url' => $redirectUrl]);
    }

    public function sync(Request $request, OpenBankingConnection $connection): JsonResponse
    {
        if ($connection->status !== 'active') {
            return response()->json(['error' => 'Connection is not active'], 422);
        }

        $synced = app(OpenBankingService::class)->syncTransactions($connection);

        return response()->json(['synced' => $synced]);
    }

    /**
     * PATCH /api/v1/accounting/open-banking/transactions/{transaction}
     */
    public function updateTransaction(Request $request, AccBankFeedTransaction $transaction): BankFeedTransactionResource
    {
        $validated = $request->validate([
            'status' => ['sometimes', 'in:new,matched,ignored'],
            'journal_entry_id' => ['sometimes', 'nullable', 'integer'],
            'category' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        if (isset($validated['journal_entry_id']) && $validated['journal_entry_id'] !== null) {
            $this->service->matchToJournalEntry($transaction, (int) $validated['journal_entry_id']);
        } elseif (isset($validated['status']) && $validated['status'] === 'ignored') {
            $transaction->update(['status' => 'ignored']);
        } else {
            $transaction->update($validated);
        }

        $transaction->refresh();

        return new BankFeedTransactionResource($transaction);
    }
}
