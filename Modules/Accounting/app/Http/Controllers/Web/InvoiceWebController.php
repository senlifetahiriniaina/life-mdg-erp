<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Accounting\Models\Invoice;

class InvoiceWebController extends Controller
{
    public function index(Request $request): Response
    {
        $invoices = Invoice::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->latest('invoice_date')
            ->paginate(25)->withQueryString();

        return Inertia::render('Accounting/Invoices/Index', ['invoices' => $invoices]);
    }
}
