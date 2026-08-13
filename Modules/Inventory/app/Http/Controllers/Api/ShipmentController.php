<?php

declare(strict_types=1);

namespace Modules\Inventory\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Inventory\Http\Resources\CarrierResource;
use Modules\Inventory\Http\Resources\ShipmentResource;
use Modules\Inventory\Models\Carrier;
use Modules\Inventory\Models\Shipment;
use Modules\Inventory\Services\ShippingService;

/**
 * @group Controllers - Shipment
 *
 * Shipments and tracking.
 */
class ShipmentController extends Controller
{
    public function __construct(
        private readonly ShippingService $service,
    ) {}

    /**
     * GET /api/v1/inventory/shipments
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Shipment::with('carrier')->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('carrier_id')) {
            $query->where('carrier_id', $request->integer('carrier_id'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->string('date_from'));
        }

        return ShipmentResource::collection($query->paginate(25));
    }

    /**
     * POST /api/v1/inventory/shipments
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'carrier_id' => ['required', 'exists:inventory_carriers,id'],
            'order_id' => ['nullable', 'integer'],
            'origin_address' => ['required', 'array'],
            'origin_address.name' => ['required', 'string'],
            'origin_address.street' => ['required', 'string'],
            'origin_address.city' => ['required', 'string'],
            'origin_address.zip' => ['required', 'string'],
            'origin_address.country' => ['required', 'string', 'size:2'],
            'destination_address' => ['required', 'array'],
            'destination_address.name' => ['required', 'string'],
            'destination_address.street' => ['required', 'string'],
            'destination_address.city' => ['required', 'string'],
            'destination_address.zip' => ['required', 'string'],
            'destination_address.country' => ['required', 'string', 'size:2'],
            'weight_kg' => ['required', 'numeric', 'min:0.001'],
            'dimensions' => ['nullable', 'array'],
            'dimensions.length' => ['nullable', 'numeric'],
            'dimensions.width' => ['nullable', 'numeric'],
            'dimensions.height' => ['nullable', 'numeric'],
            'service_type' => ['nullable', 'in:express,standard,economy'],
            'estimated_cost' => ['nullable', 'numeric', 'min:0'],
            'estimated_delivery_at' => ['nullable', 'date'],
        ]);

        $shipment = $this->service->createShipment($validated);

        return (new ShipmentResource($shipment))->response()->setStatusCode(200);
    }

    /**
     * GET /api/v1/inventory/shipments/{shipment}
     */
    public function show(Shipment $shipment): ShipmentResource
    {
        return new ShipmentResource($shipment->load(['carrier', 'events']));
    }

    /**
     * PATCH /api/v1/inventory/shipments/{shipment}
     */
    public function update(Request $request, Shipment $shipment): ShipmentResource
    {
        $validated = $request->validate([
            'status' => ['sometimes', 'in:draft,booked,picked_up,in_transit,delivered,returned,failed'],
            'tracking_number' => ['sometimes', 'nullable', 'string'],
            'actual_cost' => ['sometimes', 'nullable', 'numeric'],
            'estimated_delivery_at' => ['sometimes', 'nullable', 'date'],
            'location' => ['sometimes', 'nullable', 'string'],
        ]);

        if (isset($validated['status'])) {
            $location = $validated['location'] ?? '';
            $this->service->updateStatus($shipment, $validated['status'], $location);
            unset($validated['status'], $validated['location']);
        }

        if (! empty($validated)) {
            $shipment->update($validated);
        }

        return new ShipmentResource($shipment->fresh(['carrier', 'events']));
    }

    /**
     * DELETE /api/v1/inventory/shipments/{shipment}
     */
    public function destroy(Shipment $shipment): JsonResponse
    {
        $shipment->delete();

        return response()->json(null, 204);
    }

    /**
     * POST /api/v1/inventory/shipments/rates
     */
    public function rates(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'origin_address' => ['required', 'array'],
            'origin_address.name' => ['required', 'string'],
            'origin_address.street' => ['required', 'string'],
            'origin_address.city' => ['required', 'string'],
            'origin_address.country' => ['required', 'string'],
            'destination_address' => ['required', 'array'],
            'destination_address.name' => ['required', 'string'],
            'destination_address.street' => ['required', 'string'],
            'destination_address.city' => ['required', 'string'],
            'destination_address.country' => ['required', 'string'],
            'weight_kg' => ['required', 'numeric', 'min:0.001'],
        ]);

        $rates = $this->service->getRates(
            $validated['origin_address'],
            $validated['destination_address'],
            (float) $validated['weight_kg'],
        );

        return response()->json(['data' => $rates]);
    }

    /**
     * GET /api/v1/inventory/shipments/{shipment}/track
     */
    public function track(Shipment $shipment): JsonResponse
    {
        $events = $this->service->track($shipment);

        return response()->json([
            'shipment' => new ShipmentResource($shipment->load('carrier')),
            'events' => $events,
        ]);
    }

    /**
     * GET /api/v1/inventory/carriers
     */
    public function indexCarriers(Request $request): AnonymousResourceCollection
    {
        $carriers = Carrier::withCount('shipments')->get();

        return CarrierResource::collection($carriers);
    }

    /**
     * POST /api/v1/inventory/carriers
     */
    public function storeCarrier(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:20', 'unique:inventory_carriers,code'],
            'tracking_url_template' => ['nullable', 'string'],
            'api_key' => ['nullable', 'string'],
            'active' => ['nullable', 'boolean'],
            'settings' => ['nullable', 'array'],
        ]);

        /** @var Carrier $carrier */
        $carrier = Carrier::create($validated);

        return (new CarrierResource($carrier))->response()->setStatusCode(200);
    }
}
