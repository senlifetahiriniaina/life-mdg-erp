<?php

declare(strict_types=1);

namespace Modules\Logistics\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Logistics\Models\Shipment;
use Modules\Logistics\Services\ShipmentService;

/**
 * @group Logistics - Shipments
 */
class ShipmentController extends Controller
{
    public function __construct(private readonly ShipmentService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Shipment::class);

        $q = Shipment::query()
            ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->input('type'), fn ($q, $v) => $q->where('type', $v))
            ->when($request->input('carrier_id'), fn ($q, $v) => $q->where('carrier_id', $v))
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => $q->items(),
            'meta' => [
                'total' => $q->total(),
                'per_page' => $q->perPage(),
                'current_page' => $q->currentPage(),
                'last_page' => $q->lastPage(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Shipment::class);

        $data = $request->validate([
            'reference' => 'nullable|string|max:100|unique:logistics_shipments,reference',
            'tracking_number' => 'nullable|string|max:100',
            'status' => 'nullable|string',
            'type' => 'nullable|in:outbound,inbound,transfer,return',
            'carrier_id' => 'required|integer|exists:logistics_carriers,id',
            'origin_location_id' => 'nullable|integer',
            'destination_location_id' => 'nullable|integer',
            'weight_kg' => 'nullable|numeric|min:0',
            'volume_cbm' => 'nullable|numeric|min:0',
            'transport_mode' => 'nullable|in:road,air,sea,rail,multimodal',
            'incoterm' => 'nullable|string',
            'shipper_name' => 'nullable|string|max:200',
            'shipper_address' => 'nullable|string',
            'shipper_city' => 'nullable|string|max:100',
            'shipper_country' => 'nullable|string|size:2',
            'consignee_name' => 'nullable|string|max:200',
            'consignee_address' => 'nullable|string',
            'consignee_city' => 'nullable|string|max:100',
            'consignee_country' => 'nullable|string|size:2',
            'declared_value' => 'nullable|numeric|min:0',
            'requires_cold_chain' => 'nullable|boolean',
            'has_hazmat' => 'nullable|boolean',
        ]);

        $data['created_by'] = $request->user()->id;
        $data['status'] = $data['status'] ?? 'draft';

        if (empty($data['reference'])) {
            $data['reference'] = 'SHP-'.now()->format('Ymd').'-'.rand(1000, 9999);
        }

        $shipment = Shipment::create($data);

        return response()->json(['data' => $shipment], 201);
    }

    public function show(Shipment $shipment): JsonResponse
    {
        $this->authorize('view', $shipment);

        return response()->json(['data' => $shipment]);
    }

    public function update(Request $request, Shipment $shipment): JsonResponse
    {
        $this->authorize('update', $shipment);

        $data = $request->validate([
            'status' => 'nullable|string',
            'carrier_id' => 'nullable|integer|exists:logistics_carriers,id',
            'tracking_number' => 'nullable|string|max:100',
            'consignee_name' => 'nullable|string|max:200',
            'consignee_address' => 'nullable|string',
            'consignee_city' => 'nullable|string|max:100',
            'consignee_country' => 'nullable|string|size:2',
            'incoterm' => 'nullable|string',
            'estimated_delivery_at' => 'nullable|date',
            'special_instructions' => 'nullable|string',
            'actual_cost' => 'nullable|numeric|min:0',
        ]);

        $shipment->update($data);

        return response()->json(['data' => $shipment->fresh()]);
    }

    public function destroy(Shipment $shipment): JsonResponse
    {
        $this->authorize('delete', $shipment);

        $shipment->delete();

        return response()->json(['message' => 'Deleted.']);
    }

    /**
     * Chantier 8.3: this method (and dispatch/deliver/cancel below) used to
     * set `status` directly instead of calling the injected ShipmentService,
     * so booked_at/picked_up_at and the shipment's tracking-event trail were
     * silently never written. dispatch() also set status to 'dispatched' —
     * a vocabulary that belongs to the separate, unrelated LgxShipment model
     * (see CLAUDE.md's Category B cleanup note); this module's real
     * ShipmentFactory/TrackingEventController/TrackingEventFactory all use
     * 'picked_up', which is what ShipmentService::dispatch() writes.
     */
    public function book(Request $request, Shipment $shipment): JsonResponse
    {
        $this->authorize('update', $shipment);

        $this->service->book($shipment);

        return response()->json(['data' => $shipment->fresh()]);
    }

    public function dispatch(Shipment $shipment): JsonResponse
    {
        $this->authorize('update', $shipment);

        $this->service->dispatch($shipment);

        return response()->json(['data' => $shipment->fresh()]);
    }

    public function deliver(Request $request, Shipment $shipment): JsonResponse
    {
        $this->authorize('update', $shipment);

        $data = $request->validate([
            'delivery_date' => 'nullable|date',
            'delivered_at' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);
        $this->service->deliver($shipment, $data['delivery_date'] ?? $data['delivered_at'] ?? null);

        return response()->json(['data' => $shipment->fresh()]);
    }

    public function cancel(Shipment $shipment): JsonResponse
    {
        $this->authorize('update', $shipment);

        $this->service->cancel($shipment);

        return response()->json(['data' => $shipment->fresh()]);
    }

    public function trackingHistory(Shipment $shipment): JsonResponse
    {
        $this->authorize('view', $shipment);

        return response()->json(['data' => $shipment->trackingEvents()->orderBy('recorded_at')->get()]);
    }
}
