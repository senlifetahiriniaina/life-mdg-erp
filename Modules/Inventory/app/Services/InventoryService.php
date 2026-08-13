<?php

namespace Modules\Inventory\Services;

use Illuminate\Database\Eloquent\Collection;
use Modules\Inventory\Models\Category;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ReorderRule;
use Modules\Inventory\Models\Stock;
use Modules\Inventory\Models\StockMovement;
use Modules\Inventory\Models\Warehouse;

class InventoryService
{
    public function createProduct(array $data): Product
    {
        return Product::create($data);
    }

    public function updateProduct(Product $product, array $data): Product
    {
        $product->update($data);

        return $product;
    }

    public function deleteProduct(Product $product): bool
    {
        return $product->delete();
    }

    public function getProduct(int $id): ?Product
    {
        return Product::with('category', 'stockMovements')->find($id);
    }

    public function getAllProducts($perPage = 15)
    {
        return Product::with('category')
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function getLowStockProducts(): Collection
    {
        return Product::lowStock()
            ->with('category')
            ->orderBy('name')
            ->get();
    }

    public function adjustStock(Product $product, int $quantity, string $type, string $reason, ?int $warehouseId = null, ?int $userId = null): StockMovement
    {
        return StockMovement::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouseId,
            'type' => $type,
            'quantity' => $quantity,
            'reason' => $reason,
            'created_by' => $userId,
        ]);
    }

    public function recordStockMovement(array $data): StockMovement
    {
        return StockMovement::create($data);
    }

    public function recordMovement(array $data): StockMovement
    {
        $movement = $this->recordStockMovement($data);

        // Update stock quantity and cost based on movement type with pessimistic locking
        if (isset($data['product_id']) && isset($data['warehouse_id'])) {
            $productId = $data['product_id'];
            $warehouseId = $data['warehouse_id'];
            $quantity = $data['quantity'] ?? 0;
            $type = $data['type'] ?? 'in';

            // Use pessimistic locking to prevent concurrent modification race conditions
            $stock = Stock::where('product_id', $productId)
                ->where('warehouse_id', $warehouseId)
                ->lockForUpdate()
                ->first();

            if (! $stock) {
                // Create with initial values if not found
                $stock = Stock::create([
                    'product_id' => $productId,
                    'warehouse_id' => $warehouseId,
                    'quantity' => 0,
                    'avg_cost' => 0,
                ]);

                // Re-acquire lock on newly created record
                $stock = Stock::where('product_id', $productId)
                    ->where('warehouse_id', $warehouseId)
                    ->lockForUpdate()
                    ->first();
            }

            // Validate negative stock for outbound movements
            $originalQuantity = $stock->quantity;
            $outflowTypes = ['out', 'dispatch'];
            $isOutflow = in_array($type, $outflowTypes, true);
            $newQuantity = $isOutflow ? $originalQuantity - $quantity : $originalQuantity + $quantity;

            if ($newQuantity < 0) {
                if ($type === 'out') {
                    throw new \InvalidArgumentException(
                        "Insufficient stock. Available: {$originalQuantity}, Requested: {$quantity}"
                    );
                }
                // Other outflows (e.g. dispatch) clamp at zero — never go negative.
                $newQuantity = 0;
            }

            $stock->quantity = $newQuantity;

            // Update average cost if unit cost is provided (only for 'in' movements)
            if (isset($data['unit_cost']) && $quantity > 0 && $type === 'in') {
                if ($originalQuantity == 0) {
                    $stock->avg_cost = $data['unit_cost'];
                } else {
                    $old_total_cost = $originalQuantity * $stock->avg_cost;
                    $new_total_cost = $old_total_cost + ($quantity * $data['unit_cost']);
                    $stock->avg_cost = $new_total_cost / $newQuantity;
                }
            }

            $stock->save();
        }

        return $movement;
    }

    public function getStockHistory(Product $product, int $limit = 50)
    {
        return $product->stockMovements()
            ->recent()
            ->limit($limit)
            ->get();
    }

    public function createCategory(array $data): Category
    {
        return Category::create($data);
    }

    public function updateCategory(Category $category, array $data): Category
    {
        $category->update($data);

        return $category;
    }

    public function getAllCategories($perPage = 15)
    {
        return Category::withCount('products')
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function createWarehouse(array $data): Warehouse
    {
        return Warehouse::create($data);
    }

    public function updateWarehouse(Warehouse $warehouse, array $data): Warehouse
    {
        $warehouse->update($data);

        return $warehouse;
    }

    public function getAllWarehouses($perPage = 15)
    {
        return Warehouse::withCount('stockMovements')
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function createReorderRule(array $data): ReorderRule
    {
        return ReorderRule::create($data);
    }

    public function updateReorderRule(ReorderRule $rule, array $data): ReorderRule
    {
        $rule->update($data);

        return $rule;
    }

    public function getProductsNeedingReorder()
    {
        return Product::lowStock()
            ->with('category')
            ->get();
    }

    public function getInventoryValuation()
    {
        return Product::active()
            ->with('stock')
            ->get()
            ->map(function ($product) {
                $quantity = $product->stock->sum('quantity');

                return [
                    'id' => $product->id,
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'quantity' => $quantity,
                    'cost_price' => $product->cost_price,
                    'total_value' => $quantity * $product->cost_price,
                ];
            })
            ->sortByDesc('total_value');
    }

    public function getInventoryMetrics()
    {
        $products = Product::active()->with('stock')->get();
        $totalQuantity = 0;
        $totalValue = 0;

        foreach ($products as $product) {
            $quantity = $product->stock->sum('quantity');
            $totalQuantity += $quantity;
            $totalValue += $quantity * $product->cost_price;
        }

        return [
            'total_products' => $products->count(),
            'total_value' => $totalValue,
            'low_stock_count' => Product::lowStock()->count(),
            'total_quantity' => $totalQuantity,
            'categories' => Category::count(),
            'warehouses' => Warehouse::active()->count(),
        ];
    }
}
