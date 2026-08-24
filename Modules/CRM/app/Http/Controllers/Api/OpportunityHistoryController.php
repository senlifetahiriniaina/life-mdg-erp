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
    /**
     * Chantier 32.15: had zero authorize() call — any authenticated CRM-module user of any
     * company could view any other company's opportunity's field-by-field change history
     * (old_value/new_value on amount, stage, etc.) by id, bypassing the already-correct
     * OpportunityPolicy that gates OpportunityController::show() on the exact same record.
     */
    public function index(Request $request, Opportunity $opportunity): JsonResponse
    {
        $this->authorize('view', $opportunity);

        $history = OpportunityHistory::where('opportunity_id', $opportunity->id)
            ->with('editor:id,name')
            ->orderByDesc('changed_at')
            ->paginate($request->integer('per_page', 50));

        return response()->json($history);
    }
}
