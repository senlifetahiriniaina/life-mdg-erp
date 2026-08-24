<?php

namespace Modules\Inventory\Http\Controllers\Web;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Modules\Inventory\Http\Controllers\Api\Concerns\ScopesToCompany;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Services\InventoryService;

/**
 * Chantier 32: index()/show()/edit() had zero company/tenant scoping —
 * server-rendering another company's product catalog/detail into Inertia
 * props is a real leak independent of any API fix (the JSON is embedded in
 * the page response regardless of whether the frontend reads it) — same
 * "the Web layer needs its own inline fix" precedent Achats' Chantier 19
 * Web/PurchaseOrderController already established.
 */
class ProductController extends Controller
{
    use ScopesToCompany;

    public function __construct(protected InventoryService $service) {}

    public function index(Request $request)
    {
        $query = $this->scopeToCompany(Product::query(), $request);

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

    public function show(Request $request, $id)
    {
        $product = $this->service->getProduct($id);
        abort_unless($product !== null && $product->company_id === $this->companyId($request), 404);

        return Inertia::render('Inventory/Products/Show', [
            'product' => $product,
        ]);
    }

    public function edit(Request $request, $id)
    {
        $product = $this->service->getProduct($id);
        abort_unless($product !== null && $product->company_id === $this->companyId($request), 404);

        return Inertia::render('Inventory/Products/Form', [
            'product' => $product,
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
