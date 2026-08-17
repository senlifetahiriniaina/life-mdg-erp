<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Logistics\Http\Controllers\Api\CarrierController;
use Modules\Logistics\Http\Controllers\Api\CarrierRateController;
use Modules\Logistics\Http\Controllers\Api\CustomsDeclarationController;
use Modules\Logistics\Http\Controllers\Api\CustomsRouteController;
use Modules\Logistics\Http\Controllers\Api\DeliveryRoundController;
use Modules\Logistics\Http\Controllers\Api\FreightInvoiceController;
use Modules\Logistics\Http\Controllers\Api\LocationController;
use Modules\Logistics\Http\Controllers\Api\LogisticsAnalyticsController;
use Modules\Logistics\Http\Controllers\Api\PutawayRuleController;
use Modules\Logistics\Http\Controllers\Api\RouteController;
use Modules\Logistics\Http\Controllers\Api\ShipmentController;
use Modules\Logistics\Http\Controllers\Api\TrackingEventController;

// Default: Simple GET throttle (1000 req/min)
Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user', 'module:Logistics', 'role:logistics-manager,warehouse-operator,manager,admin', 'throttle:simple_get'])->prefix('v1')->group(function () {
    // Shipments (core TMS)
    Route::get('logistics/shipments', [ShipmentController::class, 'index']);
    Route::get('logistics/shipments/{shipment}', [ShipmentController::class, 'show']);
    Route::get('logistics/shipments/{shipment}/tracking', [ShipmentController::class, 'trackingHistory']);
    Route::middleware('throttle:create_post')->group(function () {
        Route::post('logistics/shipments', [ShipmentController::class, 'store']);
        Route::put('logistics/shipments/{shipment}', [ShipmentController::class, 'update']);
        Route::delete('logistics/shipments/{shipment}', [ShipmentController::class, 'destroy']);
        Route::post('logistics/shipments/{shipment}/book', [ShipmentController::class, 'book']);
        Route::post('logistics/shipments/{shipment}/dispatch', [ShipmentController::class, 'dispatch']);
        Route::post('logistics/shipments/{shipment}/deliver', [ShipmentController::class, 'deliver']);
        Route::post('logistics/shipments/{shipment}/cancel', [ShipmentController::class, 'cancel']);
        Route::post('logistics/shipments/{shipment}/tracking-events', [TrackingEventController::class, 'store']);
    });

    // Carriers
    Route::get('logistics/carriers', [CarrierController::class, 'index']);
    Route::get('logistics/carriers/{carrier}', [CarrierController::class, 'show']);
    Route::middleware('throttle:complex_get')->group(function () {
        Route::get('logistics/carriers/{carrier}/performance', [CarrierController::class, 'performance']);
    });
    Route::middleware('throttle:create_post')->group(function () {
        Route::post('logistics/carriers', [CarrierController::class, 'store']);
        Route::put('logistics/carriers/{carrier}', [CarrierController::class, 'update']);
        Route::delete('logistics/carriers/{carrier}', [CarrierController::class, 'destroy']);
        Route::post('logistics/carriers/select', [CarrierController::class, 'select']);
    });

    // Carrier Rates
    Route::get('logistics/carrier-rates', [CarrierRateController::class, 'index']);
    Route::get('logistics/carrier-rates/{carierRate}', [CarrierRateController::class, 'show']);
    Route::middleware('throttle:complex_get')->group(function () {
        Route::post('logistics/carrier-rates/estimate', [CarrierRateController::class, 'estimate']);
    });
    Route::middleware('throttle:create_post')->group(function () {
        Route::post('logistics/carrier-rates', [CarrierRateController::class, 'store']);
        Route::put('logistics/carrier-rates/{carierRate}', [CarrierRateController::class, 'update']);
        Route::delete('logistics/carrier-rates/{carierRate}', [CarrierRateController::class, 'destroy']);
    });

    // VRP Route Optimizer — declared BEFORE {route} wildcard to avoid capture
    Route::middleware('throttle:expensive')->group(function () {
        Route::post('logistics/routes/optimize', [\Modules\Logistics\Http\Controllers\Api\RouteOptimizationController::class, 'optimize'])
            ->name('logistics.routes.optimize');
        Route::get('logistics/routes/optimize/{jobId}/result', [\Modules\Logistics\Http\Controllers\Api\RouteOptimizationController::class, 'result'])
            ->name('logistics.routes.optimize.result')
            ->where('jobId', '[a-zA-Z0-9_.]+');
    });

    // Routes
    Route::get('logistics/routes', [RouteController::class, 'index']);
    Route::get('logistics/routes/{route}', [RouteController::class, 'show']);
    Route::middleware('throttle:create_post')->group(function () {
        Route::post('logistics/routes', [RouteController::class, 'store']);
        Route::put('logistics/routes/{route}', [RouteController::class, 'update']);
        Route::delete('logistics/routes/{route}', [RouteController::class, 'destroy']);
    });

    // Delivery Rounds (Last Mile)
    Route::get('logistics/delivery-rounds', [DeliveryRoundController::class, 'index']);
    Route::get('logistics/delivery-rounds/{deliveryRound}', [DeliveryRoundController::class, 'show']);
    Route::middleware('throttle:create_post')->group(function () {
        Route::post('logistics/delivery-rounds', [DeliveryRoundController::class, 'store']);
        Route::put('logistics/delivery-rounds/{deliveryRound}', [DeliveryRoundController::class, 'update']);
        Route::delete('logistics/delivery-rounds/{deliveryRound}', [DeliveryRoundController::class, 'destroy']);
        Route::post('logistics/delivery-rounds/{deliveryRound}/start', [DeliveryRoundController::class, 'start']);
        Route::post('logistics/delivery-rounds/{deliveryRound}/complete', [DeliveryRoundController::class, 'complete']);
        Route::post('logistics/delivery-rounds/{deliveryRound}/optimize', [DeliveryRoundController::class, 'optimize']);
        Route::post('logistics/delivery-rounds/{deliveryRound}/stops', [DeliveryRoundController::class, 'addStop']);
        Route::post('logistics/delivery-rounds/{deliveryRound}/stops/{stop}/pod', [DeliveryRoundController::class, 'proofOfDelivery']);
    });

    // Freight Invoices
    Route::get('logistics/freight-invoices', [FreightInvoiceController::class, 'index']);
    Route::get('logistics/freight-invoices/{freightInvoice}', [FreightInvoiceController::class, 'show']);
    Route::middleware('throttle:create_post')->group(function () {
        Route::post('logistics/freight-invoices', [FreightInvoiceController::class, 'store']);
        Route::put('logistics/freight-invoices/{freightInvoice}', [FreightInvoiceController::class, 'update']);
        Route::delete('logistics/freight-invoices/{freightInvoice}', [FreightInvoiceController::class, 'destroy']);
        Route::post('logistics/freight-invoices/{freightInvoice}/approve', [FreightInvoiceController::class, 'approve']);
        Route::post('logistics/freight-invoices/{freightInvoice}/dispute', [FreightInvoiceController::class, 'dispute']);
    });

    // Customs Declarations
    Route::get('logistics/customs-declarations', [CustomsDeclarationController::class, 'index']);
    Route::get('logistics/customs-declarations/{customsDeclaration}', [CustomsDeclarationController::class, 'show']);
    Route::middleware('throttle:create_post')->group(function () {
        Route::post('logistics/customs-declarations', [CustomsDeclarationController::class, 'store']);
        Route::put('logistics/customs-declarations/{customsDeclaration}', [CustomsDeclarationController::class, 'update']);
        Route::delete('logistics/customs-declarations/{customsDeclaration}', [CustomsDeclarationController::class, 'destroy']);
        Route::post('logistics/customs-declarations/{customsDeclaration}/submit', [CustomsDeclarationController::class, 'submit']);
    });

    // Warehouse Locations (hierarchy must be before apiResource to avoid {location} capture)
    Route::get('logistics/locations/hierarchy', [LocationController::class, 'hierarchy']);
    Route::get('logistics/locations', [LocationController::class, 'index']);
    Route::get('logistics/locations/{location}', [LocationController::class, 'show']);
    Route::middleware('throttle:create_post')->group(function () {
        Route::post('logistics/locations', [LocationController::class, 'store']);
        Route::put('logistics/locations/{location}', [LocationController::class, 'update']);
        Route::delete('logistics/locations/{location}', [LocationController::class, 'destroy']);
    });

    // Putaway Rules
    Route::get('logistics/putaway-rules', [PutawayRuleController::class, 'index']);
    Route::get('logistics/putaway-rules/{putawayRule}', [PutawayRuleController::class, 'show']);
    Route::middleware('throttle:create_post')->group(function () {
        Route::post('logistics/putaway-rules', [PutawayRuleController::class, 'store']);
        Route::put('logistics/putaway-rules/{putawayRule}', [PutawayRuleController::class, 'update']);
        Route::delete('logistics/putaway-rules/{putawayRule}', [PutawayRuleController::class, 'destroy']);
    });

    // Analytics (complex calculations)
    Route::middleware('throttle:complex_get')->group(function () {
        Route::get('logistics/analytics/kpis', [LogisticsAnalyticsController::class, 'kpis']);
        Route::get('logistics/analytics/carrier-performance', [LogisticsAnalyticsController::class, 'carrierPerformance']);
        Route::get('logistics/analytics/shipment-stats', [LogisticsAnalyticsController::class, 'shipmentStats']);
        Route::get('logistics/analytics/co2', [LogisticsAnalyticsController::class, 'co2Emissions']);
    });
});

// ── AI Assisted First — Contextual AI guidance ────────────────────────────
Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user'])->prefix('v1/logistics')->group(function () {
    Route::post('ai/assist', [\Modules\Logistics\Http\Controllers\Api\LogisticsAiAssistController::class, 'assist'])
        ->name('logistics.ai.assist');
});

// ── HS Code catalogue (read-only, WCO 2022 nomenclature) ──────────────────
Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user', 'module:Logistics', 'throttle:simple_get'])->prefix('v1')->group(function () {
    Route::get('logistics/hs-codes/search', [\Modules\Logistics\Http\Controllers\Api\HsCodeController::class, 'search'])
        ->name('logistics.hs-codes.search');
    Route::get('logistics/hs-codes/chapters', [\Modules\Logistics\Http\Controllers\Api\HsCodeController::class, 'chapters'])
        ->name('logistics.hs-codes.chapters');
    Route::get('logistics/hs-codes/{code}', [\Modules\Logistics\Http\Controllers\Api\HsCodeController::class, 'show'])
        ->name('logistics.hs-codes.show');
});

// ── Ocean/Air Visibility Aggregator ──────────────────────────────────────
Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user'])->prefix('v1')->group(function () {
    Route::get('logistics/shipments/{id}/visibility', [\Modules\Logistics\Http\Controllers\Api\ShipmentVisibilityController::class, 'visibility'])
        ->name('logistics.shipments.visibility');
    Route::post('logistics/shipments/{id}/refresh-tracking', [\Modules\Logistics\Http\Controllers\Api\ShipmentVisibilityController::class, 'refreshTracking'])
        ->name('logistics.shipments.refresh-tracking');
    Route::get('logistics/shipments/{id}/tracking-events', [\Modules\Logistics\Http\Controllers\Api\ShipmentVisibilityController::class, 'trackingEvents'])
        ->name('logistics.shipments.tracking-events');
});

// ── Phase 47 — Customs Clearance + Route Optimization + Vehicles + Carrier Integrations ──
// NOTE: routesIndex()/routesStore() (GET/POST logistics/routes) are deliberately NOT routed
// here — they collide with the already-active RouteController::index/store on the same path.
Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user', 'module:Logistics', 'role:logistics-manager,warehouse-operator,manager,admin', 'throttle:simple_get'])->prefix('v1')->group(function () {
    Route::get('logistics/customs', [CustomsRouteController::class, 'customsIndex']);
    Route::get('logistics/customs/document-checklist', [CustomsRouteController::class, 'customsDocumentChecklist']);
    Route::get('logistics/customs/{id}', [CustomsRouteController::class, 'customsShow']);
    Route::get('logistics/vehicles', [CustomsRouteController::class, 'vehiclesIndex']);
    Route::get('logistics/routes/driver/{driverId}', [CustomsRouteController::class, 'routesDriverView']);

    Route::middleware('throttle:create_post')->group(function () {
        Route::post('logistics/customs', [CustomsRouteController::class, 'customsStore']);
        Route::post('logistics/customs/hs-code-suggest', [CustomsRouteController::class, 'customsHsCodeSuggest']);
        Route::post('logistics/customs/{id}/calculate-duties', [CustomsRouteController::class, 'customsCalculateDuties']);
        Route::put('logistics/customs/{id}/submit', [CustomsRouteController::class, 'customsSubmit']);
        Route::put('logistics/customs/{id}/clear', [CustomsRouteController::class, 'customsClear']);

        Route::put('logistics/routes/{id}/optimize', [CustomsRouteController::class, 'routesOptimize']);
        Route::put('logistics/routes/{id}/start', [CustomsRouteController::class, 'routesStart']);
        Route::put('logistics/routes/{id}/complete', [CustomsRouteController::class, 'routesComplete']);
        Route::put('logistics/routes/{routeId}/stops/{stopId}/complete', [CustomsRouteController::class, 'routeStopComplete']);

        Route::post('logistics/carriers/{id}/track', [CustomsRouteController::class, 'carrierTrack']);
        Route::get('logistics/carriers/{id}/rate', [CustomsRouteController::class, 'carrierRate']);
    });
});
