<?php

declare(strict_types=1);

namespace Modules\Logistics\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Logistics\Models\Carrier;
use Modules\Logistics\Services\CarrierSelectionService;

/**
 * @group Logistics - Carriers
 */
class CarrierController extends Controller
{
    public function __construct(private readonly CarrierSelectionService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Carrier::class);

        $q = Carrier::query()
            ->when($request->input('type'), fn ($q, $v) => $q->where('type', $v))
            ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
            ->withCount('shipments')
            ->latest()
            ->paginate(20);

        $data = $q->items();
        return response()->json([
            'data' => $data,
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
        $this->authorize('create', Carrier::class);

        $data = $request->validate([
            'name' => 'required|string|max:200',
            'code' => 'nullable|string|max:10|unique:logistics_carriers,code',
            'type' => 'nullable|in:road,air,sea,rail,multimodal',
            'status' => 'nullable|in:active,inactive',
            'contact_email' => 'nullable|email',
            'contact_phone' => 'nullable|string|max:30',
            'website' => 'nullable|url',
            'country' => 'nullable|string|size:2',
            'tracking_url_template' => 'nullable|string',
            'api_provider' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
        ]);

        $carrier = Carrier::create($data);
        return response()->json(['data' => $carrier], 201);
    }

    public function show(Carrier $carrier): JsonResponse
    {
        $this->authorize('view', $carrier);

        return response()->json(['data' => $carrier->load('rates')]);
    }

    public function rates(Carrier $carrier): JsonResponse
    {
        $this->authorize('view', $carrier);

        $rates = $carrier->rates()->get();
        return response()->json(['data' => $rates]);
    }

    public function update(Request $request, Carrier $carrier): JsonResponse
    {
        $this->authorize('update', $carrier);

        $data = $request->validate([
            'name' => 'sometimes|string|max:200',
            'type' => 'sometimes|nullable|in:road,air,sea,rail,multimodal',
            'status' => 'nullable|in:active,inactive',
            'contact_email' => 'nullable|email',
            'contact_phone' => 'nullable|string|max:30',
            'website' => 'nullable|url',
            'is_active' => 'nullable|boolean',
            'notes' => 'nullable|string',
        ]);

        $carrier->update($data);

        return response()->json(['data' => $carrier->fresh()]);
    }

    public function destroy(Carrier $carrier): JsonResponse
    {
        $this->authorize('delete', $carrier);

        $carrier->delete();

        return response()->json(['message' => 'Deleted.']);
    }

    public function performance(Carrier $carrier): JsonResponse
    {
        $this->authorize('view', $carrier);

        $stats = $this->service->getCarrierPerformance($carrier);

        return response()->json($stats);
    }

    public function select(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Carrier::class);

        $data = $request->validate([
            'origin_country' => 'required|string|size:2',
            'destination_country' => 'required|string|size:2',
            'transport_mode' => 'nullable|in:road,air,sea,rail,multimodal',
            'weight_kg' => 'nullable|numeric|min:0',
            'volume_cbm' => 'nullable|numeric|min:0',
            'requires_cold_chain' => 'nullable|boolean',
            'priority' => 'nullable|in:cheapest,fastest,greenest',
        ]);

        $options = $this->service->selectCarrier($data);

        return response()->json(['data' => $options]);
    }
}
