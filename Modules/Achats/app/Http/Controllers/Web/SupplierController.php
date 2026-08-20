<?php

namespace Modules\Achats\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Modules\Achats\Models\Supplier;

class SupplierController extends Controller
{
    public function index()
    {
        return Inertia::render('Achats/Suppliers/Index');
    }

    public function create()
    {
        return Inertia::render('Achats/Suppliers/Form');
    }

    public function show(Supplier $supplier)
    {
        // Chantier 19: no company check at all — a direct URL visit could
        // server-render another company's supplier detail into the page
        // payload regardless of any API-layer fix.
        abort_unless($supplier->company_id === request()->user()?->company_id, 404);

        return Inertia::render('Achats/Suppliers/Show', [
            'supplier' => $supplier,
        ]);
    }

    public function edit(Supplier $supplier)
    {
        abort_unless($supplier->company_id === request()->user()?->company_id, 404);

        return Inertia::render('Achats/Suppliers/Form', [
            'supplier' => $supplier,
        ]);
    }
}
