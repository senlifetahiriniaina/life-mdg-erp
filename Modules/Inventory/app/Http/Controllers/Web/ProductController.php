<?php

namespace Modules\Inventory\Http\Controllers\Web;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Services\InventoryService;

class ProductController extends Controller
{
    public function __construct(protected InventoryService $service) {}

    public function index(Request $request)
    {
        $query = Product::query();

        if ($search = $request->get('search')) {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('sku', 'like', "%{$search}%"));
        }

        return Inertia::render('Inventory/Products/Index', [
            'products' => $query->paginate(20),
            'filters'  => $request->only(['search']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Inventory/Products/Form', [
            'categories' => $this->service->getAllCategories()->get(),
            'method' => 'POST',
        ]);
    }

    public function store()
    {
        return redirect()->route('inventory.products.index');
    }

    public function show($id)
    {
        return Inertia::render('Inventory/Products/Show', [
            'product' => $this->service->getProduct($id),
        ]);
    }

    public function edit($id)
    {
        return Inertia::render('Inventory/Products/Form', [
            'product' => $this->service->getProduct($id),
            'categories' => $this->service->getAllCategories()->get(),
            'method' => 'PATCH',
        ]);
    }

    public function update()
    {
        return redirect()->route('inventory.products.index');
    }

    public function destroy()
    {
        return redirect()->route('inventory.products.index');
    }
}
