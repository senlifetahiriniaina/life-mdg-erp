<?php

namespace Modules\Inventory\Http\Controllers\Web;

use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Modules\Inventory\Services\InventoryService;

class CategoryController extends Controller
{
    public function __construct(protected InventoryService $service) {}

    public function index()
    {
        return Inertia::render('Inventory/Categories/Index');
    }

    public function create()
    {
        return Inertia::render('Inventory/Categories/Form');
    }

    public function store()
    {
        return redirect()->route('inventory.categories.index');
    }

    public function show($id)
    {
        return Inertia::render('Inventory/Categories/Show', [
            'category' => $this->service->getAllCategories()->find($id),
        ]);
    }

    public function edit($id)
    {
        return Inertia::render('Inventory/Categories/Form', [
            'category' => $this->service->getAllCategories()->find($id),
        ]);
    }

    public function update()
    {
        return redirect()->route('inventory.categories.index');
    }

    public function destroy()
    {
        return redirect()->route('inventory.categories.index');
    }
}
