<?php

namespace Modules\Achats\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Modules\Achats\Models\RFQ;
use Modules\Achats\Models\RFQLine;

/**
 * @group Controllers - RFQLine
 *
 * Manage RFQLine resources.
 */
class RFQLineController extends Controller
{
    public function index(RFQ $rfq)
    {
        return $rfq->lines()->get();
    }

    public function store(RFQ $rfq)
    {
        // Implementation to follow
    }

    public function show(RFQ $rfq, RFQLine $rfq_line)
    {
        return $rfq_line;
    }

    public function update(RFQ $rfq, RFQLine $rfq_line)
    {
        // Implementation to follow
    }

    public function destroy(RFQ $rfq, RFQLine $rfq_line)
    {
        // Implementation to follow
    }
}
