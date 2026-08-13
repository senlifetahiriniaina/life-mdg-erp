<?php

declare(strict_types=1);

namespace Modules\Logistics\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Logistics\Models\Carrier;
use Modules\Logistics\Models\CarrierRate;

/**
 * @group Logistics - Carrier Rates
 */
class CarrierRateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = CarrierRate::with('carrier:id,name,type')
            ->when($request->input('carrier_id'), fn ($q, $v) => $q->where('carrier_id', $v))
            ->when($request->input('mode'), fn ($q, $v) => $q->where('mode', $v))
            ->when($request->input('origin_country'), fn ($q, $v) => $q->where('origin_country', $v))
            ->when($request->input('destination_country'), fn ($q, $v) => $q->where('destination_country', $v))
            ->when($request->input('active') !== null, fn ($q) => $q->where('is_active', $request->boolean('active')))
            ->latest()
            ->paginate(20);

        return response()->json($q);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'carrier_id' => 'required|integer|exists:logistics_carriers,id',
            'name' => 'required|string|max:200',
            'mode' => 'required|in:road,air,sea,rail,multimodal',
            'origin_country' => 'required|string|size:2',
            'destination_country' => 'required|string|size:2',
            'origin_zone' => 'nullable|string|max:100',
            'destination_zone' => 'nullable|string|max:100',
            'rate_type' => 'required|in:flat,per_kg,per_km,per_piece,per_cbm',
            'base_rate' => 'required|numeric|min:0',
            'fuel_surcharge_pct' => 'nullable|numeric|min:0|max:100',
            'insurance_rate_pct' => 'nullable|numeric|min:0|max:100',
            'min_charge' => 'nullable|numeric|min:0',
            'currency' => 'required|string|size:3',
            'transit_days' => 'nullable|integer|min:0',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date|after_or_equal:valid_from',
            'is_active' => 'nullable|boolean',
        ]);

        return response()->json(CarrierRate::create($data), 201);
    }

    public function show(CarrierRate $carrierRate): JsonResponse
    {
        return response()->json($carrierRate->load('carrier:id,name,type'));
    }

    public function update(Request $request, CarrierRate $carrierRate): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:200',
            'mode' => 'sometimes|in:road,air,sea,rail,multimodal',
            'rate_type' => 'sometimes|in:flat,per_kg,per_km,per_piece,per_cbm',
            'base_rate' => 'sometimes|numeric|min:0',
            'fuel_surcharge_pct' => 'nullable|numeric|min:0|max:100',
            'insurance_rate_pct' => 'nullable|numeric|min:0|max:100',
            'min_charge' => 'nullable|numeric|min:0',
            'currency' => 'sometimes|string|size:3',
            'transit_days' => 'nullable|integer|min:0',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date|after_or_equal:valid_from',
            'is_active' => 'nullable|boolean',
        ]);

        $carrierRate->update($data);

        return response()->json($carrierRate->fresh());
    }

    public function destroy(CarrierRate $carrierRate): JsonResponse
    {
        $carrierRate->delete();

        return response()->json(null, 204);
    }

    public function estimate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'origin_country' => 'required|string|size:2',
            'destination_country' => 'required|string|size:2',
            'weight_kg' => 'required|numeric|min:0',
            'distance_km' => 'nullable|numeric|min:0',
            'mode' => 'nullable|in:road,air,sea,rail,multimodal',
        ]);

        $rates = CarrierRate::with('carrier:id,name')
            ->where('origin_country', $data['origin_country'])
            ->where('destination_country', $data['destination_country'])
            ->where('is_active', true)
            ->when(isset($data['mode']), fn ($q) => $q->where('mode', $data['mode']))
            ->get();

        abort_if($rates->isEmpty(), 404, 'No matching carrier rates found.');

        $results = $rates->map(function (CarrierRate $rate) use ($data): array {
            $cost = $rate->estimateCost(
                (float) $data['weight_kg'],
                (float) ($data['distance_km'] ?? 0)
            );

            return [
                'carrier_rate_id' => $rate->id,
                'carrier_name' => $rate->carrier?->name,
                'rate_name' => $rate->name,
                'mode' => $rate->mode,
                'currency' => $rate->currency,
                'estimated_cost' => round($cost, 2),
                'transit_days' => $rate->transit_days,
            ];
        })->sortBy('estimated_cost')->values();

        return response()->json(['data' => $results]);
    }
}
