<?php

declare(strict_types=1);

namespace Modules\CRM\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\CRM\Models\Opportunity;
use Modules\CRM\Models\OpportunityHistory;

/**
 * @group CRM - Pipeline History
 */
class OpportunityHistoryController extends Controller
{
    public function index(Request $request, Opportunity $opportunity): JsonResponse
    {
        $history = OpportunityHistory::where('opportunity_id', $opportunity->id)
            ->with('editor:id,name')
            ->orderByDesc('changed_at')
            ->paginate($request->integer('per_page', 50));

        return response()->json($history);
    }
}
