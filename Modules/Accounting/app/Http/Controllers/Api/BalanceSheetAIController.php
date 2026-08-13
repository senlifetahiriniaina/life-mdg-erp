<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Accounting\Services\AI\BalanceSheetSummaryService;

/**
 * @group Accounting
 *
 * Manage BalanceSheetAI resources in Accounting module.
 */
class BalanceSheetAIController extends Controller
{
    public function __construct(private readonly BalanceSheetSummaryService $service) {}

    public function summarize(Request $request)
    {
        $summary = $this->service->generateSummary(
            $request->month ?? '',
            $request->locale ?? 'fr'
        );

        return response()->json(['summary' => $summary]);
    }
}
