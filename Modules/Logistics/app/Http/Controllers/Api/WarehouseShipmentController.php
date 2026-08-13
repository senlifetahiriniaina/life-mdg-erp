<?php

declare(strict_types=1);

namespace Modules\Logistics\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Logistics\Models\LgxCarrier;
use Modules\Logistics\Models\LgxShipment;
use Modules\Logistics\Models\Warehouse;
use Modules\Logistics\Services\LgxShipmentService;
use Modules\Logistics\Services\WarehouseService;

class WarehouseShipmentController extends Controller
{
    public function __construct(
        private readonly WarehouseService    $warehouseService,
        private readonly LgxShipmentService  $shipmentService,
    ) {}

    // ===========================================================
    // WAREHOUSES
    // ===========================================================

    /**
     * GET /api/v1/logistics/warehouses
     */
    public function listWarehouses(Request $request): JsonResponse
    {
        $companyId = (int) $request->query('company_id', 1);
        $status    = $request->query('status');

        $query = Warehouse::forCompany($companyId)->with('zones');

        if ($status !== null) {
            $query->where('status', $status);
        }

        $warehouses = $query->orderBy('name')->get();

        return response()->json([
            'data'  => $warehouses,
            'total' => $warehouses->count(),
        ]);
    }

    /**
     * POST /api/v1/logistics/warehouses
     */
    public function createWarehouse(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_id'   => 'required|integer',
            'name'         => 'required|string|max:150',
            'code'         => 'required|string|max:20|unique:wh_warehouses,code',
            'type'         => 'required|in:main,transit,virtual,3pl',
            'address'      => 'nullable|string|max:255',
            'city'         => 'nullable|string|max:100',
            'country_code' => 'nullable|string|size:2',
            'lat'          => 'nullable|numeric',
            'lng'          => 'nullable|numeric',
            'surface_m2'   => 'nullable|numeric|min:0',
            'status'       => 'in:active,inactive',
            'manager_id'   => 'nullable|integer',
        ]);

        $warehouse = Warehouse::create($validated);

        return response()->json(['data' => $warehouse], 201);
    }

    /**
     * GET /api/v1/logistics/warehouses/{id}
     */
    public function showWarehouse(int $id): JsonResponse
    {
        $warehouse = Warehouse::with(['zones.locations'])->findOrFail($id);

        return response()->json(['data' => $warehouse]);
    }

    /**
     * GET /api/v1/logistics/warehouses/{id}/stock
     */
    public function warehouseStock(int $id): JsonResponse
    {
        $stock = $this->warehouseService->getStockByLocation($id);

        return response()->json([
            'warehouse_id' => $id,
            'data'         => $stock,
            'total_lines'  => count($stock),
        ]);
    }

    /**
     * GET /api/v1/logistics/warehouses/{id}/kpis
     */
    public function warehouseKpis(int $id): JsonResponse
    {
        $kpis = $this->warehouseService->getWarehouseKpis($id);

        return response()->json([
            'warehouse_id' => $id,
            'kpis'         => $kpis,
        ]);
    }

    /**
     * POST /api/v1/logistics/warehouses/receive
     */
    public function receiveGoods(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_id'    => 'required|integer',
            'warehouse_id'  => 'required|integer',
            'product_id'    => 'required|integer',
            'qty'           => 'required|numeric|min:0.0001',
            'unit'          => 'nullable|string|max:20',
            'lot_number'    => 'nullable|string|max:80',
            'serial_number' => 'nullable|string|max:80',
            'reference'     => 'nullable|string|max:80',
            'reference_type' => 'nullable|string|max:60',
            'operator_id'   => 'nullable|integer',
            'notes'         => 'nullable|string',
        ]);

        $result = $this->warehouseService->receiveGoods($validated);

        return response()->json([
            'message'          => 'Marchandises réceptionnées avec succès.',
            'movement_id'      => $result['movement']->id,
            'location'         => $result['location'],
            'put_away_strategy' => $result['put_away_strategy'],
        ], 201);
    }

    /**
     * POST /api/v1/logistics/warehouses/transfer
     */
    public function transferStock(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_id'         => 'required|integer',
            'from_warehouse_id'  => 'required|integer',
            'to_warehouse_id'    => 'required|integer|different:from_warehouse_id',
            'product_id'         => 'required|integer',
            'qty'                => 'required|numeric|min:0.0001',
            'unit'               => 'nullable|string|max:20',
            'lot_number'         => 'nullable|string|max:80',
            'serial_number'      => 'nullable|string|max:80',
            'reference'          => 'nullable|string|max:80',
            'operator_id'        => 'nullable|integer',
            'notes'              => 'nullable|string',
        ]);

        $result = $this->warehouseService->transferBetweenWarehouses($validated);

        return response()->json([
            'message'  => 'Transfert inter-entrepôts enregistré.',
            'outbound' => $result['outbound']->id,
            'inbound'  => $result['inbound']->id,
        ], 201);
    }

    // ===========================================================
    // SHIPMENTS
    // ===========================================================

    /**
     * GET /api/v1/logistics/shipments
     */
    public function listShipments(Request $request): JsonResponse
    {
        $companyId = (int) $request->query('company_id', 1);
        $status    = $request->query('status');
        $type      = $request->query('type');
        $perPage   = (int) $request->query('per_page', 20);

        $query = LgxShipment::with(['carrier', 'originWarehouse', 'destWarehouse'])
            ->forCompany($companyId)
            ->orderByDesc('created_at');

        if ($status !== null) {
            $query->withStatus($status);
        }

        if ($type !== null) {
            $query->where('type', $type);
        }

        $shipments = $query->paginate($perPage);

        // Append computed attributes
        $shipments->getCollection()->transform(function (LgxShipment $s): LgxShipment {
            $s->append(['status_color', 'inco_term_description', 'is_overdue_flag']);

            return $s;
        });

        return response()->json($shipments);
    }

    /**
     * POST /api/v1/logistics/shipments
     */
    public function createShipment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_id'          => 'required|integer',
            'type'                => 'in:outbound,inbound,transfer',
            'carrier_id'          => 'nullable|integer',
            'carrier_service'     => 'nullable|string|max:80',
            'origin_warehouse_id' => 'nullable|integer',
            'origin_address'      => 'nullable|array',
            'dest_warehouse_id'   => 'nullable|integer',
            'dest_address'        => 'nullable|array',
            'incoterm'            => 'nullable|in:EXW,FCA,FAS,FOB,CFR,CIF,CPT,CIP,DAP,DDP,DPU',
            'weight_kg'           => 'nullable|numeric|min:0',
            'volume_m3'           => 'nullable|numeric|min:0',
            'declared_value'      => 'nullable|numeric|min:0',
            'currency'            => 'nullable|string|size:3',
            'estimated_delivery'  => 'nullable|date',
            'notes'               => 'nullable|string',
            'created_by'          => 'required|integer',
            'items'               => 'nullable|array',
            'items.*.product_id'  => 'required_with:items|integer',
            'items.*.qty_ordered' => 'required_with:items|numeric|min:0.0001',
            'items.*.unit'        => 'nullable|string|max:20',
            'items.*.lot_number'  => 'nullable|string|max:80',
        ]);

        $shipment = $this->shipmentService->create($validated);

        return response()->json([
            'message'  => "Expédition {$shipment->reference} créée avec succès.",
            'data'     => $shipment,
        ], 201);
    }

    /**
     * GET /api/v1/logistics/shipments/{id}
     */
    public function showShipment(int $id): JsonResponse
    {
        $shipment = LgxShipment::with([
            'carrier',
            'originWarehouse',
            'destWarehouse',
            'items',
            'trackingEvents',
        ])->findOrFail($id);

        $timeline = $this->shipmentService->getTrackingTimeline($id);

        return response()->json([
            'data'     => $shipment->append(['status_color', 'inco_term_description', 'tracking_url']),
            'timeline' => $timeline,
        ]);
    }

    /**
     * PUT /api/v1/logistics/shipments/{id}/confirm
     */
    public function confirmShipment(int $id): JsonResponse
    {
        $shipment = $this->shipmentService->confirm($id);

        return response()->json([
            'message' => "Expédition {$shipment->reference} confirmée.",
            'data'    => $shipment,
        ]);
    }

    /**
     * PUT /api/v1/logistics/shipments/{id}/dispatch
     */
    public function dispatchShipment(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'tracking_number' => 'required|string|max:100',
            'carrier_id'      => 'required|integer',
        ]);

        $shipment = $this->shipmentService->dispatch(
            $id,
            $validated['tracking_number'],
            (int) $validated['carrier_id'],
        );

        return response()->json([
            'message' => "Expédition {$shipment->reference} remise au transporteur.",
            'data'    => $shipment,
        ]);
    }

    /**
     * PUT /api/v1/logistics/shipments/{id}/deliver
     */
    public function deliverShipment(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'proof_of_delivery' => 'nullable|string',
            'pod_signed_by'     => 'nullable|string|max:150',
            'delivered_at'      => 'nullable|date',
        ]);

        $shipment = $this->shipmentService->deliver($id, $validated);

        return response()->json([
            'message' => "Livraison de {$shipment->reference} enregistrée.",
            'data'    => $shipment,
        ]);
    }

    /**
     * POST /api/v1/logistics/shipments/{id}/tracking
     */
    public function addTrackingEvent(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status'             => 'required|string|max:80',
            'description'        => 'required|string|max:255',
            'location'           => 'nullable|string|max:150',
            'lat'                => 'nullable|numeric',
            'lng'                => 'nullable|numeric',
            'carrier_event_code' => 'nullable|string|max:50',
            'occurred_at'        => 'nullable|date',
        ]);

        $event = $this->shipmentService->updateTracking($id, $validated);

        return response()->json([
            'message' => 'Événement de suivi enregistré.',
            'data'    => $event,
        ], 201);
    }

    /**
     * GET /api/v1/logistics/shipments/kpis
     */
    public function shipmentKpis(Request $request): JsonResponse
    {
        $companyId = (int) $request->query('company_id', 1);
        $period    = (string) $request->query('period', now()->format('Y-m'));

        $kpis = $this->shipmentService->getShipmentKpis($companyId, $period);

        return response()->json(['kpis' => $kpis]);
    }

    /**
     * GET /api/v1/logistics/carriers
     */
    public function listCarriers(Request $request): JsonResponse
    {
        $companyId   = (int) $request->query('company_id', 1);
        $countryCode = $request->query('country');

        $query = LgxCarrier::active()->forCompany($companyId);

        if ($countryCode !== null) {
            $query->servingCountry((string) $countryCode);
        }

        $carriers = $query->orderBy('name')->get();

        return response()->json([
            'data'  => $carriers,
            'total' => $carriers->count(),
        ]);
    }

    /**
     * POST /api/v1/logistics/shipments/{id}/calculate-cost
     */
    public function calculateCost(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'carrier_id' => 'required|integer',
        ]);

        $cost = $this->shipmentService->calculateShippingCost($id, (int) $validated['carrier_id']);

        return response()->json(['data' => $cost]);
    }
}
