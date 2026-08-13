<?php

namespace Modules\Inventory\Http\Controllers\Web;

use Illuminate\Routing\Controller;
use Inertia\Inertia;

class StockAdjustmentController extends Controller
{
    public function index()
    {
        return Inertia::render('Inventory/StockAdjustments/Index');
    }

    public function create()
    {
        return Inertia::render('Inventory/StockAdjustments/Form');
    }

    public function store()
    {
        return redirect()->route('inventory.stock-adjustments.index');
    }

    public function show($id)
    {
        return Inertia::render('Inventory/StockAdjustments/Show');
    }

    public function edit($id)
    {
        return Inertia::render('Inventory/StockAdjustments/Form');
    }

    public function update()
    {
        return redirect()->route('inventory.stock-adjustments.index');
    }

    public function destroy()
    {
        return redirect()->route('inventory.stock-adjustments.index');
    }
}
