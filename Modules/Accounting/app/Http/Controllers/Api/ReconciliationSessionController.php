<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Accounting\Models\BankAccount;
use Modules\Accounting\Models\ReconciliationSession;
use Modules\Accounting\Services\BankReconciliationService;

/**
 * @group Accounting
 *
 * Manage ReconciliationSession resources in Accounting module.
 */
class ReconciliationSessionController extends Controller
{
    public function __construct(
        private readonly BankReconciliationService $service,
    ) {}

    public function index(BankAccount $bankAccount): JsonResponse
    {
        return response()->json($bankAccount->reconciliationSessions()->latest()->get());
    }

    public function store(Request $request, BankAccount $bankAccount): JsonResponse
    {
        $validated = $request->validate([
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'opening_balance' => ['required', 'numeric'],
        ]);

        $session = ReconciliationSession::create(array_merge($validated, [
            'bank_account_id' => $bankAccount->id,
            'created_by' => $request->user()->id,
            'status' => 'in_progress',
        ]));

        return response()->json($session, 201);
    }

    public function show(BankAccount $bankAccount, ReconciliationSession $reconciliationSession): JsonResponse
    {
        return response()->json($reconciliationSession->load('createdBy'));
    }

    public function destroy(BankAccount $bankAccount, ReconciliationSession $reconciliationSession): JsonResponse
    {
        $reconciliationSession->delete();

        return response()->json(null, 204);
    }

    public function complete(BankAccount $bankAccount, ReconciliationSession $reconciliationSession): JsonResponse
    {
        if ($reconciliationSession->status === 'completed') {
            return response()->json(['message' => 'Session already completed.'], 422);
        }

        $this->service->completeReconciliation($reconciliationSession);

        return response()->json($reconciliationSession->fresh());
    }
}
