<?php

declare(strict_types=1);

namespace Modules\Logistics\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Logistics\Models\LogisticsRoute;

/**
 * @group Logistics - Routes
 */
class RouteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = LogisticsRoute::with('carrier:id,name,type')
            ->when($request->input('mode'), fn ($q, $v) => $q->where('mode', $v))
            ->when($request->input('carrier_id'), fn ($q, $v) => $q->where('carrier_id', $v))
            ->when($request->input('origin_country'), fn ($q, $v) => $q->where('origin_country', $v))
            ->when($request->input('destination_country'), fn ($q, $v) => $q->where('destination_country', $v))
            ->when($request->input('active') !== null, fn ($q) => $q->where('is_active', $request->boolean('active')))
            ->when($request->input('search'), fn ($q, $v) => $q->where(fn ($sq) => $sq->where('name', 'like', "%{$v}%")
                ->orWhere('code', 'like', "%{$v}%")
                ->orWhere('origin_name', 'like', "%{$v}%")
                ->orWhere('destination_name', 'like', "%{$v}%")))
            ->latest()
            ->paginate(20);

        return response()->json($q);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:200',
            'code' => 'nullable|string|max:20|unique:logistics_routes,code',
            'origin_name' => 'required|string|max:200',
            'origin_country' => 'required|string|size:2',
            'origin_address' => 'nullable|string',
            'destination_name' => 'required|string|max:200',
            'destination_country' => 'required|string|size:2',
            'destination_address' => 'nullable|string',
            'mode' => 'required|in:road,air,sea,rail,multimodal',
            'distance_km' => 'nullable|integer|min:0',
            'estimated_transit_days' => 'nullable|integer|min:0',
            'carrier_id' => 'nullable|integer|exists:logistics_carriers,id',
            'is_active' => 'nullable|boolean',
            'notes' => 'nullable|string',
        ]);

        return response()->json(LogisticsRoute::create($data), 201);
    }

    public function show(LogisticsRoute $route): JsonResponse
    {
        return response()->json($route->load('carrier:id,name,type'));
    }

    public function update(Request $request, LogisticsRoute $route): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:200',
            'origin_name' => 'sometimes|string|max:200',
            'origin_country' => 'sometimes|string|size:2',
            'origin_address' => 'nullable|string',
            'destination_name' => 'sometimes|string|max:200',
            'destination_country' => 'sometimes|string|size:2',
            'destination_address' => 'nullable|string',
            'mode' => 'sometimes|in:road,air,sea,rail,multimodal',
            'distance_km' => 'nullable|integer|min:0',
            'estimated_transit_days' => 'nullable|integer|min:0',
            'carrier_id' => 'nullable|integer|exists:logistics_carriers,id',
            'is_active' => 'nullable|boolean',
            'notes' => 'nullable|string',
        ]);

        $route->update($data);

        return response()->json($route->fresh());
    }

    public function destroy(LogisticsRoute $route): JsonResponse
    {
        abort_if(
            $route->shipments()->whereNotIn('status', ['delivered', 'cancelled'])->exists(),
            422,
            'Cannot delete route with active shipments.'
        );

        $route->delete();

        return response()->json(null, 204);
    }

    // Chantier 32.23 (deep 14-layer audit, layer 9 — fake/dead): optimize()
    // used to live here as a hollow stub (echoed `shipment_ids` back
    // unchanged, hardcoded 0km/0min) with zero route ever pointing at it —
    // confirmed via a repo-wide grep of routes/api.php, mort confirmé, à
    // supprimer. The real, live VRP solver already exists and is properly
    // routed at POST logistics/routes/optimize via
    // RouteOptimizationController::optimize()/RouteOptimizerService (2-opt
    // improvement, time windows, vehicle capacity) — this was a dead,
    // redundant duplicate of a feature that already works for real, not a
    // gap needing activation.
}
