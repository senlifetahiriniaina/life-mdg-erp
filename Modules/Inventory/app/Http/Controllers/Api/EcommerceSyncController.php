<?php

declare(strict_types=1);

namespace Modules\Inventory\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Inventory\Jobs\SyncInventoryToEcommerceJob;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Services\EcommerceSyncService;

/**
 * @group Inventory - Ecommerce Sync
 */
class EcommerceSyncController extends Controller
{
    public function __construct(private readonly EcommerceSyncService $service) {}

    /**
     * Sync a single product to Ecommerce.
     */
    public function syncProduct(Product $product): JsonResponse
    {
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
