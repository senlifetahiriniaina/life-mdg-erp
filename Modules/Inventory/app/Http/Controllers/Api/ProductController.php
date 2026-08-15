<?php

namespace Modules\Inventory\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Inventory\Http\Requests\StoreProductRequest;
use Modules\Inventory\Http\Requests\UpdateProductRequest;
use Modules\Inventory\Http\Resources\ProductResource;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Services\InventoryService;

/**
 * @group Controllers - Product
 *
 * Products and catalog.
 */
class ProductController extends Controller
{
    public function __construct(protected InventoryService $service) {}

    /**
     * List products with eager loading to prevent N+1 queries.
     *
     * @queryParam search string Search by name or SKU
     * @queryParam category integer Filter by category ID
     * @queryParam status string Filter by status (active/inactive)
     * @queryParam per_page integer Results per page (default 15, max 100)
     */
    public function index(Request $request)
    {
        $search = $request->query('search');
        $category = $request->query('category');
        $status = $request->query('status');
        $priceMin = $request->query('price_min');
        $priceMax = $request->query('price_max');
        $sortParam = $request->query('sort', '-created_at');
        $perPage = min((int) ($request->query('per_page', 15)), 100);

        $query = Product::with('category');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%$search%")
                    ->orWhere('sku', 'LIKE', "%$search%");
            });
        }

        if ($category) {
            $query->where(function ($q) use ($category) {
                $q->where('category', $category)
                  ->orWhere('category_id', $category);
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        // Legacy is_active filter
        if ($request->has('is_active')) {
            $isActive = filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN);
            $query->where('is_active', $isActive);
        }

        if ($priceMin !== null) {
            $query->where('selling_price', '>=', $priceMin);
        }

        if ($priceMax !== null) {
            $query->where('selling_price', '<=', $priceMax);
        }

        $sortDir = str_starts_with($sortParam, '-') ? 'desc' : 'asc';
        $sortCol = ltrim($sortParam, '-');
        $allowed = ['name', 'cost_price', 'selling_price', 'sale_price', 'status', 'sku', 'created_at'];
        if (in_array($sortCol, $allowed)) {
            $query->orderBy($sortCol, $sortDir);
        } else {
            $query->latest('created_at');
        }

        return response()->json($query->paginate($perPage));
    }

    public function store(StoreProductRequest $request)
    {
        $data = $request->validated();

        // Validate selling_price >= cost_price
        if (isset($data['selling_price']) && isset($data['cost_price'])) {
            if ((float) $data['selling_price'] < (float) $data['cost_price']) {
                return response()->json([
                    'message' => 'Validation failed.',
                    'errors' => ['selling_price' => ['Selling price must be greater than or equal to cost price']],
                ], 422);
            }
        }

        // Sync sale_price and selling_price
        if (!empty($data['selling_price']) && empty($data['sale_price'])) {
            $data['sale_price'] = $data['selling_price'];
        } elseif (!empty($data['sale_price']) && empty($data['selling_price'])) {
            $data['selling_price'] = $data['sale_price'];
        }

        // Add tenant_id from authenticated user
        $data['tenant_id'] = auth()->user()->tenant_id ?? null;

        $product = $this->service->createProduct($data);

        return response()->json(new ProductResource($product), 201);
    }

    /**
     * Show a single product with all related data.
     */
    public function show(Product $product)
    {
        // Eager load all relationships to prevent N+1 queries on detail view
        $product->load('category', 'stockMovements', 'supplier');

        return new ProductResource($product);
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        $data = $request->validated();

        // Validate selling_price >= cost_price
        $sellingPrice = $data['selling_price'] ?? (float) $product->selling_price;
        $costPrice = $data['cost_price'] ?? (float) $product->cost_price;
        if (isset($data['selling_price']) || isset($data['cost_price'])) {
            if ((float) $sellingPrice < (float) $costPrice) {
                return response()->json([
                    'message' => 'Validation failed.',
                    'errors' => ['selling_price' => ['Selling price must be greater than or equal to cost price']],
                ], 422);
            }
        }

        // Sync sale_price and selling_price
        if (!empty($data['selling_price'])) {
            $data['sale_price'] = $data['selling_price'];
        }

        $updated = $this->service->updateProduct($product, $data);

        return new ProductResource($updated);
    }

    public function destroy(Product $product)
    {
        // Only inactive products can be deleted
        $status = $product->status ?? ($product->is_active ? 'active' : 'inactive');
        if ($status === 'active') {
            return response()->json(['message' => 'Cannot delete active products.'], 403);
        }

        $this->service->deleteProduct($product);

        return response()->noContent();
    }

    public function lowStock()
    {
        $products = $this->service->getLowStockProducts();

        return ProductResource::collection($products);
    }

    public function lowStockReport()
    {
        $stocks = \Modules\Inventory\Models\Stock::with(['product'])
            ->get()
            ->filter(function ($stock) {
                $reorderLevel = (int) ($stock->product?->reorder_level ?? $stock->product?->reorder_point ?? 0);
                return $reorderLevel > 0 && $stock->quantity < $reorderLevel;
            })
            ->values();

        return response()->json([
            'low_stock_items' => $stocks->map(fn ($s) => [
                'product_id' => $s->product_id,
                'product_name' => $s->product?->name,
                'quantity' => (float) $s->quantity,
                'reorder_level' => (int) ($s->product?->reorder_level ?? $s->product?->reorder_point ?? 0),
                'warehouse_id' => $s->warehouse_id,
            ]),
        ]);
    }

    public function metrics()
    {
        return response()->json($this->service->getInventoryMetrics());
    }

    public function valuation()
    {
        $stocks = \Modules\Inventory\Models\Stock::with(['product', 'warehouse'])->get();

        $totalValue = 0;
        $byWarehouse = [];

        foreach ($stocks as $stock) {
            $value = (float) $stock->quantity * (float) ($stock->avg_cost ?? $stock->product?->cost_price ?? 0);
            $totalValue += $value;
            $warehouseId = $stock->warehouse_id;
            if (!isset($byWarehouse[$warehouseId])) {
                $byWarehouse[$warehouseId] = ['warehouse_id' => $warehouseId, 'value' => 0];
            }
            $byWarehouse[$warehouseId]['value'] += $value;
        }

        return response()->json([
            'total_value' => $totalValue,
            'by_warehouse' => array_values($byWarehouse),
        ]);
    }

    public function adjustStock(Request $request, Product $product, $warehouseId)
    {
        $data = $request->validate([
            'quantity' => 'required|numeric|min:0',
            'reason' => 'nullable|string',
        ]);

        $stock = \Modules\Inventory\Models\Stock::firstOrCreate(
            ['product_id' => $product->id, 'warehouse_id' => $warehouseId],
            ['quantity' => 0, 'reserved_quantity' => 0]
        );

        $stock->update(['quantity' => $data['quantity']]);

        $reorderLevel = (int) ($product->reorder_level ?? $product->reorder_point ?? 0);
        $lowStockAlert = $reorderLevel > 0 && $data['quantity'] < $reorderLevel;

        return response()->json([
            'product_id' => $product->id,
            'warehouse_id' => (int) $warehouseId,
            'quantity' => (float) $data['quantity'],
            'low_stock_alert' => $lowStockAlert,
        ]);
    }

    public function transferStock(Request $request, Product $product)
    {
        $data = $request->validate([
            'from_warehouse_id' => 'required|integer',
            'to_warehouse_id' => 'required|integer',
            'quantity' => 'required|numeric|min:0.01',
        ]);

        $fromStock = \Modules\Inventory\Models\Stock::where('product_id', $product->id)
            ->where('warehouse_id', $data['from_warehouse_id'])
            ->first();

        if (!$fromStock || $fromStock->quantity < $data['quantity']) {
            return response()->json(['message' => 'Insufficient stock for transfer.', 'errors' => ['quantity' => ['Insufficient stock']]], 422);
        }

        $fromStock->decrement('quantity', $data['quantity']);

        $toStock = \Modules\Inventory\Models\Stock::firstOrCreate(
            ['product_id' => $product->id, 'warehouse_id' => $data['to_warehouse_id']],
            ['quantity' => 0, 'reserved_quantity' => 0]
        );
        $toStock->increment('quantity', $data['quantity']);

        return response()->json(['message' => 'Stock transferred successfully.']);
    }

    public function stock(Product $product)
    {
        $stocks = $product->stock()
            ->with('warehouse', 'location')
            ->get();

        $totalQuantity = $stocks->sum('quantity');
        $totalReserved = $stocks->sum('reserved_quantity');

        return response()->json([
            'id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'total_quantity' => $totalQuantity,
            'total_reserved' => $totalReserved,
            'available_quantity' => $totalQuantity - $totalReserved,
            'by_warehouse' => $stocks,
        ]);
    }
}
