<?php

namespace Modules\Achats\Http\Controllers\Api;

use Illuminate\Routing\Controller;
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

    public function index()
    {
        // Implementation to follow
    }

    public function store()
    {
        // Implementation to follow
    }

    public function show(RFQ $rfq)
    {
        return $rfq->load(['lines', 'quotes']);
    }

    public function update(RFQ $rfq)
    {
        // Implementation to follow
    }

    public function issue(RFQ $rfq)
    {
        // Implementation to follow
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
