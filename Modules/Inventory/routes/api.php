<?php

use Illuminate\Support\Facades\Route;
use Modules\Inventory\Http\Controllers\Api\BarcodeController;
use Modules\Inventory\Http\Controllers\Api\CategoryController;
use Modules\Inventory\Http\Controllers\Api\CrossdockController;
use Modules\Inventory\Http\Controllers\Api\CycleCountController;
use Modules\Inventory\Http\Controllers\Api\DemandForecastController;
use Modules\Inventory\Http\Controllers\Api\EcommerceSyncController;
use Modules\Inventory\Http\Controllers\Api\InventoryAIController;
use Modules\Inventory\Http\Controllers\Api\LotTrackingController;
use Modules\Inventory\Http\Controllers\Api\PickingOrderController;
use Modules\Inventory\Http\Controllers\Api\ProductController;
use Modules\Inventory\Http\Controllers\Api\PurchaseOrderController;
use Modules\Inventory\Http\Controllers\Api\RmaController;
use Modules\Inventory\Http\Controllers\Api\SeasonalFactorController;
use Modules\Inventory\Http\Controllers\Api\ShipmentController;
use Modules\Inventory\Http\Controllers\Api\StockMovementController;
use Modules\Inventory\Http\Controllers\Api\SupplierController;
use Modules\Inventory\Http\Controllers\Api\TransferOrderController;
use Modules\Inventory\Http\Controllers\Api\ValuationController;
use Modules\Inventory\Http\Controllers\Api\WarehouseController;
use Modules\Inventory\Http\Controllers\Api\WavePickingController;

// Default: Simple GET throttle (1000 req/min)
Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user', 'module:Inventory', 'role:employee,logistics-manager,warehouse-operator,purchasing-manager,inventory-analyst,manager,admin', 'throttle:simple_get'])->group(function () {
    // Ecommerce Sync routes (write operations)
    Route::middleware('throttle:create_post')->group(function () {
        Route::post('sync/ecommerce/product/{product}', [EcommerceSyncController::class, 'syncProduct']);
        Route::post('sync/ecommerce/all', [EcommerceSyncController::class, 'syncAll']);
    });
    Route::get('sync/ecommerce/status', [EcommerceSyncController::class, 'status']);

    // Product specific routes (complex analytics)
    Route::middleware('throttle:complex_get')->group(function () {
        Route::get('products/low-stock', [ProductController::class, 'lowStock']);
        Route::get('products/metrics', [ProductController::class, 'metrics']);
        Route::get('products/{product}/stock', [ProductController::class, 'stock']);
        Route::get('products/{product}/history', [StockMovementController::class, 'productHistory']);
    });
    Route::middleware('throttle:expensive')->group(function () {
        Route::get('products/valuation', [ProductController::class, 'valuation']);
        Route::get('valuation', [ProductController::class, 'valuation']);
        Route::get('low-stock', [ProductController::class, 'lowStockReport']);
    });

    Route::middleware('throttle:create_post')->group(function () {
        Route::patch('products/{product}/stock/{warehouseId}', [ProductController::class, 'adjustStock']);
        Route::post('products/{product}/transfer', [ProductController::class, 'transferStock']);
    });

    // Lot specific routes
    Route::middleware('throttle:complex_get')->group(function () {
        Route::get('lots/expiring', [LotTrackingController::class, 'expiring']);
        Route::get('lots/{lot}/movements', [LotTrackingController::class, 'movements']);
        Route::get('lots/stats/{productId}', [LotTrackingController::class, 'stats']);
    });
    Route::middleware('throttle:create_post')->group(function () {
        Route::post('lots/{lot}/receive', [LotTrackingController::class, 'receive']);
        Route::post('lots/{lot}/issue', [LotTrackingController::class, 'issue']);
        Route::post('lots/{lot}/transfer', [LotTrackingController::class, 'transfer']);
        Route::post('lots/{lot}/quarantine', [LotTrackingController::class, 'quarantine']);
    });

    // Valuation specific routes (expensive calculations)
    Route::middleware('throttle:expensive')->group(function () {
        Route::post('valuation/receive', [ValuationController::class, 'receiveCostLayer']);
        Route::get('valuation/summary', [ValuationController::class, 'summary']);
        Route::get('valuation/total-value', [ValuationController::class, 'totalValue']);
        Route::get('valuation/by-warehouse', [ValuationController::class, 'byWarehouse']);
        Route::get('valuation/product-value', [ValuationController::class, 'productValue']);
        Route::get('valuation/layers', [ValuationController::class, 'activeLayers']);
        Route::get('valuation/runs', [ValuationController::class, 'indexRuns']);
        Route::post('valuation/run', [ValuationController::class, 'runValuation']);
        Route::get('valuation/runs/{run}', [ValuationController::class, 'showRun']);
    });

    // Forecast specific routes (expensive AI calculations)
    Route::middleware('throttle:expensive')->group(function () {
        Route::post('demand-forecasts/generate', [DemandForecastController::class, 'generate']);
        Route::post('demand-forecasts/reconcile', [DemandForecastController::class, 'reconcile']);
    });
    Route::middleware('throttle:complex_get')->group(function () {
        Route::get('demand-forecasts/expiring', [DemandForecastController::class, 'expiring']);
    });

    // Transfer Order specific routes
    Route::middleware('throttle:complex_get')->group(function () {
        Route::get('transfer-orders/pending', [TransferOrderController::class, 'pending']);
        Route::get('redistribution/rules', [TransferOrderController::class, 'indexRules']);
        Route::get('redistribution/rebalancing-analysis', [TransferOrderController::class, 'rebalancingAnalysis']);
        Route::get('warehouses/{warehouse}/transfer-history', [TransferOrderController::class, 'transferHistory']);
    });
    Route::middleware('throttle:create_post')->group(function () {
        Route::post('transfer-orders/{transfer}/approve', [TransferOrderController::class, 'approve']);
        Route::post('transfer-orders/{transfer}/ship', [TransferOrderController::class, 'ship']);
        Route::post('transfer-orders/{transfer}/receive', [TransferOrderController::class, 'receive']);
        Route::post('transfer-orders/{transfer}/cancel', [TransferOrderController::class, 'cancel']);
    });

    // Redistribution specific routes
    Route::middleware('throttle:create_post')->group(function () {
        Route::post('redistribution/rules', [TransferOrderController::class, 'storeRule']);
        Route::post('redistribution/auto-suggest', [TransferOrderController::class, 'autoSuggest']);
    });

    // Picking specific routes
    Route::middleware('throttle:complex_get')->group(function () {
        Route::get('picking-orders/next', [PickingOrderController::class, 'next']);
        Route::get('picking/{pickingOrder}/next-pick', [PickingOrderController::class, 'nextPick']);
    });
    Route::middleware('throttle:create_post')->group(function () {
        Route::post('picking-orders/{picking}/record', [PickingOrderController::class, 'record']);
        Route::post('picking-orders/{picking}/assign', [PickingOrderController::class, 'assign']);
        Route::post('picking-orders/{picking}/lines/{pickingLine}/pick', [PickingOrderController::class, 'pickLine']);
        Route::post('picking-orders/{picking}/complete', [PickingOrderController::class, 'complete']);
    });

    // Cycle Count specific routes
    Route::middleware('throttle:create_post')->group(function () {
        Route::post('cycle-counts/{cycleCount}/record', [CycleCountController::class, 'record']);
        Route::post('cycle-counts/{cycleCount}/validate', [CycleCountController::class, 'validate']);
        Route::post('cycle-counts/{cycleCount}/lines/{cycleCountLine}/count', [CycleCountController::class, 'countLine']);
    });

    // Barcode routes
    Route::middleware('throttle:create_post')->group(function () {
        Route::post('barcodes/lookup', [BarcodeController::class, 'lookup']);
    });
    Route::get('barcode/product/{barcode}', [BarcodeController::class, 'lookupProduct']);
    Route::get('barcode/location/{barcode}', [BarcodeController::class, 'lookupLocation']);

    // AI routes (expensive)
    Route::middleware('throttle:expensive')->group(function () {
        Route::post('ai/forecast-demand', [InventoryAIController::class, 'forecastDemand']);
        Route::post('ai/suggest-reorder', [InventoryAIController::class, 'suggestReorder']);
        Route::post('ai/analyze-anomalies', [InventoryAIController::class, 'analyzeAnomalies']);
        Route::post('ai/classify-abc', [InventoryAIController::class, 'classifyABC']);
        Route::post('ai/detect-obsolete', [InventoryAIController::class, 'detectObsolete']);
    });

    // Purchase Order specific routes
    Route::middleware('throttle:create_post')->group(function () {
        Route::post('purchase-orders/{purchaseOrder}/send', [PurchaseOrderController::class, 'send']);
        Route::post('purchase-orders/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receive']);
    });

    // Shipment specific routes
    Route::middleware('throttle:complex_get')->group(function () {
        Route::get('shipments/{shipment}/track', [ShipmentController::class, 'track']);
    });
    Route::middleware('throttle:create_post')->group(function () {
        Route::post('shipments/rates', [ShipmentController::class, 'rates']);
    });

    // Carrier routes (managed through ShipmentController)
    Route::get('carriers', [ShipmentController::class, 'indexCarriers']);
    Route::middleware('throttle:create_post')->group(function () {
        Route::post('carriers', [ShipmentController::class, 'storeCarrier']);
    });

    // Seasonal Factor specific routes
    Route::middleware('throttle:create_post')->group(function () {
        Route::post('seasonal-factors/upsert', [SeasonalFactorController::class, 'upsert']);
    });

    // RMA specific routes
    Route::middleware('throttle:create_post')->group(function () {
        Route::post('rmas/{rma}/approve', [RmaController::class, 'approve']);
        Route::post('rmas/{rma}/receive', [RmaController::class, 'receive']);
        Route::post('rmas/{rma}/refund', [RmaController::class, 'refund']);
    });

    // Wave picking specific routes
    Route::middleware('throttle:create_post')->group(function () {
        Route::post('waves/{wave}/start', [WavePickingController::class, 'start']);
        Route::post('waves/{wave}/lines/{wavePickLine}/pick', [WavePickingController::class, 'pickLine']);
        Route::post('waves/{wave}/complete', [WavePickingController::class, 'complete']);
    });

    // API Resources with read caching (10-minute TTL — products catalog changes infrequently)
    Route::middleware('cache.api:10')->group(function () {
        Route::apiResource('products', ProductController::class)->only(['index', 'show'])->names('api.products');
        Route::apiResource('categories', CategoryController::class)->only(['index', 'show'])->names('api.categories');
        Route::apiResource('warehouses', WarehouseController::class)->only(['index', 'show'])->names('api.warehouses');
        Route::apiResource('suppliers', SupplierController::class)->only(['index', 'show']);
        Route::apiResource('barcodes', BarcodeController::class)->only(['index', 'show']);
    });
    Route::middleware('throttle:create_post')->group(function () {
        Route::apiResource('products', ProductController::class)->only(['store', 'update', 'destroy'])->names('api.products');
        Route::apiResource('categories', CategoryController::class)->only(['store', 'update', 'destroy'])->names('api.categories');
        Route::apiResource('warehouses', WarehouseController::class)->only(['store', 'update', 'destroy'])->names('api.warehouses');
        Route::apiResource('suppliers', SupplierController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('barcodes', BarcodeController::class)->only(['store', 'update', 'destroy']);
    });

    // Operational resources (shorter TTL: 5 minutes — these change frequently)
    Route::middleware('cache.api:5')->group(function () {
        Route::apiResource('stock-movements', StockMovementController::class)->only(['index', 'show']);
        Route::apiResource('lots', LotTrackingController::class)->only(['index', 'show']);
        Route::apiResource('picking-orders', PickingOrderController::class)->only(['index', 'show']);
        Route::apiResource('shipments', ShipmentController::class)->only(['index', 'show']);
        Route::apiResource('purchase-orders', PurchaseOrderController::class)->only(['index', 'show']);
        Route::apiResource('rmas', RmaController::class)->only(['index', 'show']);
    });
    Route::middleware('throttle:create_post')->group(function () {
        Route::apiResource('stock-movements', StockMovementController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('lots', LotTrackingController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('picking-orders', PickingOrderController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('shipments', ShipmentController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('purchase-orders', PurchaseOrderController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('rmas', RmaController::class)->only(['store', 'update', 'destroy']);
    });

    // Planning resources (longer TTL: 15 minutes — static reference data)
    Route::middleware('cache.api:15')->group(function () {
        Route::apiResource('transfer-orders', TransferOrderController::class)->only(['index', 'show']);
        Route::apiResource('demand-forecasts', DemandForecastController::class)->only(['index', 'show']);
        Route::apiResource('valuations', ValuationController::class)->only(['index', 'show']);
        Route::apiResource('seasonal-factors', SeasonalFactorController::class)->only(['index', 'show']);
        Route::apiResource('cycle-counts', CycleCountController::class)->only(['index', 'show']);
    });
    Route::middleware('throttle:expensive')->group(function () {
        Route::apiResource('transfer-orders', TransferOrderController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('demand-forecasts', DemandForecastController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('valuations', ValuationController::class)->only(['store', 'update', 'destroy']);
    });
    Route::middleware('throttle:create_post')->group(function () {
        Route::apiResource('seasonal-factors', SeasonalFactorController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('cycle-counts', CycleCountController::class)->only(['store', 'update', 'destroy']);
    });

    // Crossdock operations (no caching — time-sensitive)
    Route::middleware('throttle:create_post')->group(function () {
        Route::post('crossdock/{crossdockOperation}/execute', [CrossdockController::class, 'execute']);
        Route::apiResource('crossdock', CrossdockController::class);
        Route::apiResource('waves', WavePickingController::class);
    });
});

// ── AI Assisted First — Contextual AI guidance ────────────────────────────
Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user', 'module:Inventory', 'role:employee,logistics-manager,warehouse-operator,purchasing-manager,inventory-analyst,manager,admin'])->group(function () {
    Route::post('ai/assist', [\Modules\Inventory\Http\Controllers\Api\InventoryAiAssistController::class, 'assist'])
        ->name('inventory.ai.assist');
});

// ── EDI (850/856/810) ─────────────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user', 'module:Inventory', 'role:employee,logistics-manager,warehouse-operator,purchasing-manager,inventory-analyst,manager,admin'])->group(function () {
    Route::post('edi/receive', [\Modules\Inventory\Http\Controllers\Api\EdiController::class, 'receive'])
        ->name('inventory.edi.receive');
    Route::post('edi/generate-810', [\Modules\Inventory\Http\Controllers\Api\EdiController::class, 'generate810'])
        ->name('inventory.edi.generate-810');
    Route::get('edi/transactions', [\Modules\Inventory\Http\Controllers\Api\EdiController::class, 'transactions'])
        ->name('inventory.edi.transactions');
});

// ── 3PL Fulfillment connectors ────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user', 'module:Inventory', 'role:employee,logistics-manager,warehouse-operator,purchasing-manager,inventory-analyst,manager,admin'])->group(function () {
    Route::get('3pl/connectors', [\Modules\Inventory\Http\Controllers\Api\FulfillmentController::class, 'connectors'])
        ->name('inventory.3pl.connectors');
    Route::post('3pl/fulfill', [\Modules\Inventory\Http\Controllers\Api\FulfillmentController::class, 'fulfill'])
        ->name('inventory.3pl.fulfill');
    Route::get('3pl/orders/{referenceId}/status', [\Modules\Inventory\Http\Controllers\Api\FulfillmentController::class, 'orderStatus'])
        ->name('inventory.3pl.order-status');
    Route::post('3pl/sync-inventory', [\Modules\Inventory\Http\Controllers\Api\FulfillmentController::class, 'syncInventory'])
        ->name('inventory.3pl.sync-inventory');
});
