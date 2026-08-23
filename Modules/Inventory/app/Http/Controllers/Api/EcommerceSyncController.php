<?php

declare(strict_types=1);

namespace Modules\Inventory\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Inventory\Http\Controllers\Api\Concerns\ScopesToCompany;
use Modules\Inventory\Jobs\SyncInventoryToEcommerceJob;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Services\EcommerceSyncService;

/**
 * @group Inventory - Ecommerce Sync
 *
 * `syncAll()`/`status()` are queue-wide/aggregate operations with no single
 * per-company record to scope. `syncProduct()` is the one endpoint here that
 * resolves a route-bound record — `Product` is Inventory "core" scope (owned
 * by the sibling half of this chantier, not touched here as a model file),
 * but this controller is this chantier's own, so the `company_id` column
 * that sibling migration already added is used here via the shared trait
 * rather than left unchecked.
 */
class EcommerceSyncController extends Controller
{
    use ScopesToCompany;

    public function __construct(private readonly EcommerceSyncService $service) {}

    /**
     * Sync a single product to Ecommerce.
     */
    public function syncProduct(Request $request, Product $product): JsonResponse
    {
        $this->assertSameCompany($request, $product);
        $this->service->syncProductToEcommerce($product);

        return response()->json([
            'message' => 'Product synced successfully.',
            'ecommerce_synced_at' => $product->fresh()?->ecommerce_synced_at,
        ]);
    }

    /**
     * Queue full inventory sync to Ecommerce.
     */
    public function syncAll(): JsonResponse
    {
        SyncInventoryToEcommerceJob::dispatch();

        return response()->json([
            'message' => 'Full sync queued successfully.',
            'queued_at' => now()->toISOString(),
        ]);
    }

    /**
     * Get sync status.
     */
    public function status(): JsonResponse
    {
        return response()->json($this->service->getSyncStatus());
    }
}
