<?php

namespace Modules\Inventory\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Modules\Inventory\Services\InventoryService;

class WarehouseController extends Controller
{
    public function __construct(protected InventoryService $service) {}

    public function index()
    {
        return Inertia::render('Inventory/Warehouses/Index');
    }

    public function create()
    {
        return Inertia::render('Inventory/Warehouses/Form');
    }

    public function store()
    {
        return redirect()->route('inventory.warehouses.index');
    }

    public function show($id)
    {
        return Inertia::render('Inventory/Warehouses/Show', [
            'warehouse' => $this->service->getAllWarehouses()->find($id),
        ]);
    }

    public function edit($id)
    {
        return Inertia::render('Inventory/Warehouses/Form', [
            'warehouse' => $this->service->getAllWarehouses()->find($id),
        ]);
    }

    public function update()
    {
        return redirect()->route('inventory.warehouses.index');
    }

    public function destroy()
    {
        return redirect()->route('inventory.warehouses.index');
    }
}
