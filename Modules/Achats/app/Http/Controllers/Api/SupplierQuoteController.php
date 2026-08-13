<?php

namespace Modules\Achats\Http\Controllers\Api;

use Illuminate\Routing\Controller;
use Modules\Achats\Models\RFQ;
use Modules\Achats\Models\SupplierQuote;
use Modules\Achats\Services\RFQService;

/**
 * @group Controllers - Supplier Quote
 *
 * Manage Supplier Quote resources.
 */
class SupplierQuoteController extends Controller
{
    public function __construct(protected RFQService $service) {}

    public function index()
    {
        // Implementation to follow
    }

    public function show(SupplierQuote $supplier_quote)
    {
        return $supplier_quote;
    }

    public function store(RFQ $rfq)
    {
        // Implementation to follow
    }

    public function accept(SupplierQuote $supplier_quote)
    {
        $this->service->selectWinningQuote($supplier_quote);

        return $supplier_quote->refresh();
    }

    public function reject(SupplierQuote $supplier_quote)
    {
        $this->service->rejectQuote($supplier_quote, 'Rejected');

        return $supplier_quote->refresh();
    }
}
