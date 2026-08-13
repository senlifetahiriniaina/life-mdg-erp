<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Accounting\Services\AI\TransactionCategorizationService;

/**
 * @group Accounting
 *
 * Manage TransactionAI resources in Accounting module.
 */
class TransactionAIController extends Controller
{
    public function __construct(private readonly TransactionCategorizationService $service) {}

    public function categorize(Request $request)
    {
        $result = $this->service->categorizeTransaction($request->transaction);

        return response()->json($result);
    }
}
