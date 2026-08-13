<?php

declare(strict_types=1);

namespace Modules\Inventory\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Inventory\Models\Product;

class ProductWebController extends Controller
{
    public function index(Request $request): Response
    {
        $products = Product::query()
            ->with(['category', 'unit'])
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', "%{$request->search}%")->orWhere('sku', 'like', "%{$request->search}%"))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->paginate(25)->withQueryString();

        return Inertia::render('Inventory/Products/Index', [
            'products' => $products,
            'filters' => $request->only(['search', 'type']),
        ]);
    }
}
