<?php

namespace Modules\Achats\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Achats\Models\RFQ;
use Modules\Achats\Models\RFQLine;
use Modules\Achats\Services\RFQService;

/**
 * @group Controllers - RFQLine
 *
 * Manage RFQLine resources.
 */
class RFQLineController extends Controller
{
    public function __construct(protected RFQService $service) {}

    public function index(RFQ $rfq)
    {
        return $rfq->lines()->get();
    }

    /**
     * Chantier 10: was a literal "// Implementation to follow" stub — POST
     * rfqs/{rfq}/lines silently did nothing. Wired onto
     * RFQService::addLineToRFQ(), the real method this endpoint was clearly
     * built for (it already enforces the same "only a draft RFQ can be
     * edited" rule RFQController::update() uses).
     */
    public function store(Request $request, RFQ $rfq)
    {
        $data = $request->validate([
            'product_id' => 'nullable|exists:inventory_products,id',
            'description' => 'required|string',
            'quantity' => 'required|numeric|min:0.01',
            'unit' => 'nullable|string',
            'required_date' => 'nullable|date',
            'preferred_supplier_id' => 'nullable|exists:achats_suppliers,id',
            'notes' => 'nullable|string',
        ]);

        $line = $this->service->addLineToRFQ($rfq, $data);

        return response()->json($line, 201);
    }

    public function show(RFQ $rfq, RFQLine $rfq_line)
    {
        return $rfq_line;
    }

    /**
     * Chantier 10: was a stub. RFQService has no dedicated "update a line"
     * method (only add/remove), so this mirrors the same draft-only guard
     * addLineToRFQ()/removeLineFromRFQ() already enforce, applied directly
     * to a plain model update — the smallest change that matches the
     * service's existing business rule rather than inventing a new one.
     */
    public function update(Request $request, RFQ $rfq, RFQLine $rfq_line)
    {
        abort_if(! $rfq->isDraft(), 422, 'Cannot update lines on a non-draft RFQ');

        $data = $request->validate([
            'product_id' => 'nullable|exists:inventory_products,id',
            'description' => 'sometimes|string',
            'quantity' => 'sometimes|numeric|min:0.01',
            'unit' => 'nullable|string',
            'required_date' => 'nullable|date',
            'preferred_supplier_id' => 'nullable|exists:achats_suppliers,id',
            'notes' => 'nullable|string',
        ]);

        $rfq_line->update($data);

        return $rfq_line;
    }

    /**
     * Chantier 10: was a stub. Wired onto the real
     * RFQService::removeLineFromRFQ().
     */
    public function destroy(RFQ $rfq, RFQLine $rfq_line)
    {
        $this->service->removeLineFromRFQ($rfq_line);

        return response()->noContent();
    }
}
