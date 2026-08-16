<?php

namespace Modules\Achats\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Achats\Models\RFQ;
use Modules\Achats\Services\RFQService;

/**
 * @group Controllers - RFQ
 *
 * Manage RFQ resources.
 */
class RFQController extends Controller
{
    public function __construct(protected RFQService $service) {}

    public function index(Request $request)
    {
        return RFQ::query()
            ->latest()
            ->paginate($request->get('per_page', 15));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'description' => 'nullable|string',
            'required_by_date' => 'required|date',
            'deadline_date' => 'nullable|date',
        ]);
        $data['created_by'] = auth()->id();

        $rfq = $this->service->createRFQ($data);

        return response()->json($rfq, 201);
    }

    public function show(RFQ $rfq)
    {
        return $rfq->load(['lines', 'quotes']);
    }

    public function update(Request $request, RFQ $rfq)
    {
        $data = $request->validate([
            'description' => 'nullable|string',
            'required_by_date' => 'sometimes|date',
            'deadline_date' => 'nullable|date',
        ]);

        return $this->service->updateRFQ($rfq, $data);
    }

    public function issue(Request $request, RFQ $rfq)
    {
        $data = $request->validate([
            'supplier_ids' => 'required|array|min:1',
            'supplier_ids.*' => 'exists:achats_suppliers,id',
        ]);

        $this->service->issueRFQ($rfq, $data['supplier_ids']);

        return $rfq->fresh();
    }

    public function closeRfq(RFQ $rfq)
    {
        $this->service->closeRFQ($rfq);

        return $rfq;
    }

    public function comparison(RFQ $rfq)
    {
        $comparison = $this->service->getQuoteComparison($rfq);

        return response()->json($comparison);
    }
}
