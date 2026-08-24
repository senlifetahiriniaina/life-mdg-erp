<?php

declare(strict_types=1);

namespace Modules\Inventory\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Inventory\Http\Controllers\Api\Concerns\ScopesToCompany;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Services\BarcodeService;
use Modules\Inventory\Services\InventoryService;

/**
 * @group Inventory - Barcodes
 *
 * Chantier 32: lookups were unscoped by company (a barcode from another
 * company's catalogue could be resolved and its stock detail exposed) —
 * fixed via ScopesToCompany.
 */
class BarcodeController extends Controller
{
    use ScopesToCompany;

    public function __construct(
        private readonly BarcodeService $barcodeService,
        private readonly InventoryService $inventoryService,
    ) {}

    /**
     * Lookup a product by barcode.
     */
    public function lookupProduct(Request $request, string $barcode): JsonResponse
    {
        $product = $this->barcodeService->lookupByBarcode($barcode);

        if (! $product || $product->company_id !== $this->companyId($request)) {
            return response()->json(['message' => 'Product not found.'], 404);
        }

        return response()->json([
            'product' => $product->load(['category:id,name', 'stock.warehouse:id,name', 'stock.location:id,name,code']),
        ]);
    }

    /**
     * Lookup a location by barcode.
     */
    public function lookupLocation(Request $request, string $barcode): JsonResponse
    {
        $location = $this->barcodeService->lookupLocation($barcode);

        if (! $location) {
            return response()->json(['message' => 'Location not found.'], 404);
        }

        // A Location has no company_id of its own (a bin/emplacement lives
        // under a Warehouse) — resolve the boundary through its warehouse,
        // matching this trait's assertSameCompanyViaParent shape.
        $this->assertSameCompanyViaParent($request, $location, 'warehouse');

        return response()->json(['location' => $location]);
    }

    /**
     * Quick stock movement from barcode scan.
     */
    public function stockMovement(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_barcode' => 'required|string',
            'from_location_barcode' => 'nullable|string',
            'to_location_barcode' => 'nullable|string',
            'warehouse_id' => 'required|integer|exists:inventory_warehouses,id',
            'quantity' => 'required|numeric|min:0.01',
            'type' => 'required|in:in,out,transfer,adjustment',
        ]);

        $product = $this->barcodeService->lookupByBarcode($data['product_barcode']);
        if (! $product || $product->company_id !== $this->companyId($request)) {
            return response()->json(['message' => 'Product not found.'], 404);
        }

        $warehouse = Warehouse::findOrFail($data['warehouse_id']);
        $this->assertSameCompany($request, $warehouse);

        $locationId = null;
        if (! empty($data['to_location_barcode'])) {
            $location = $this->barcodeService->lookupLocation($data['to_location_barcode']);
            $locationId = $location?->id;
        }

        $movement = $this->inventoryService->recordMovement([
            'product_id' => $product->id,
            'warehouse_id' => $data['warehouse_id'],
            'location_id' => $locationId,
            'type' => $data['type'],
            'quantity' => $data['quantity'],
            'reference' => 'SCAN-'.date('YmdHis'),
            'notes' => 'Quick stock movement via barcode scan',
            'company_id' => $this->companyId($request),
        ]);

        return response()->json([
            'movement' => $movement,
            'product' => $product->fresh(['stock']),
        ], 201);
    }
}
