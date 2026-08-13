<?php

namespace Modules\Achats\Http\Controllers\Web;

use Illuminate\Routing\Controller;
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
        return Inertia::render('Achats/Suppliers/Show', [
            'supplier' => $supplier,
        ]);
    }

    public function edit(Supplier $supplier)
    {
        return Inertia::render('Achats/Suppliers/Form', [
            'supplier' => $supplier,
        ]);
    }
}
