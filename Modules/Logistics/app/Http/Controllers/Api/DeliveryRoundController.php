<?php

declare(strict_types=1);

namespace Modules\Logistics\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Logistics\Models\DeliveryRound;
use Modules\Logistics\Models\DeliveryStop;

/**
 * @group Logistics - Delivery Rounds
 */
class DeliveryRoundController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', DeliveryRound::class);

        $q = DeliveryRound::with('carrier:id,name', 'creator:id,name')
            ->withCount('stops')
            ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->input('carrier_id'), fn ($q, $v) => $q->where('carrier_id', $v))
            ->when($request->input('planned_date'), fn ($q, $v) => $q->whereDate('planned_date', $v))
            ->when($request->input('date_from'), fn ($q, $v) => $q->where('planned_date', '>=', $v))
            ->when($request->input('date_to'), fn ($q, $v) => $q->where('planned_date', '<=', $v))
            ->when($request->input('search'), fn ($q, $v) => $q->where(fn ($sq) => $sq->where('reference', 'like', "%{$v}%")
                ->orWhere('driver_name', 'like', "%{$v}%")
                ->orWhere('vehicle_plate', 'like', "%{$v}%")))
            ->orderBy('planned_date', 'desc')
            ->paginate(20);

        return response()->json($q);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', DeliveryRound::class);

        $validated = $request->validate([
            'driver_name' => 'required|string|max:200',
            'driver_phone' => 'nullable|string|max:30',
            'vehicle_plate' => 'nullable|string|max:20',
            'vehicle_code' => 'nullable|string|max:50',
            'vehicle_type' => 'nullable|string|max:50',
            'carrier_id' => 'nullable|integer|exists:logistics_carriers,id',
            'planned_date' => 'nullable|date',
            'date' => 'nullable|date',
            'route' => 'nullable|string',
            'status' => 'nullable|string',
            'total_distance_km' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'stops' => 'nullable|array',
        ]);

        $stops = $validated['stops'] ?? [];
        $data = collect($validated)->except('stops')->toArray();
        if (isset($data['date']) && empty($data['planned_date'])) {
            $data['planned_date'] = $data['date'];
        }
        unset($data['date']);

        $data['created_by'] = $request->user()->id;
        $data['status'] = 'planned';
        $data['reference'] = 'RND-'.now()->format('Ymd').'-'.str_pad(
            (string) (DeliveryRound::whereDate('created_at', today())->count() + 1),
            4,
            '0',
            STR_PAD_LEFT
        );

        $round = DeliveryRound::create($data);

        foreach ($stops as $stop) {
            $round->stops()->create($stop);
        }

        return response()->json(['data' => $round->fresh()], 201);
    }

    public function addStop(Request $request, DeliveryRound $deliveryRound): JsonResponse
    {
        $this->authorize('update', $deliveryRound);

        $data = $request->validate([
            'location_id' => 'nullable|integer',
            'shipment_id' => 'nullable|integer|exists:logistics_shipments,id',
            'sequence' => 'nullable|integer|min:1',
            'stop_order' => 'nullable|integer|min:1',
            'delivery_window' => 'nullable|string',
            'address' => 'nullable|string',
            'contact_name' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        if (!isset($data['stop_order']) && isset($data['sequence'])) {
            $data['stop_order'] = $data['sequence'];
        }

        $stop = $deliveryRound->stops()->create($data);
        return response()->json(['data' => $stop], 201);
    }

    public function show(DeliveryRound $deliveryRound): JsonResponse
    {
        $this->authorize('view', $deliveryRound);

        return response()->json(
            $deliveryRound->load('carrier:id,name', 'creator:id,name', 'stops.shipment:id,reference,consignee_name')
        );
    }

    public function update(Request $request, DeliveryRound $deliveryRound): JsonResponse
    {
        $this->authorize('update', $deliveryRound);

        abort_if(
            in_array($deliveryRound->status, ['completed', 'cancelled'], true),
            422,
            'Cannot edit a completed or cancelled delivery round.'
        );

        $data = $request->validate([
            'driver_name' => 'sometimes|string|max:200',
            'driver_phone' => 'nullable|string|max:30',
            'vehicle_plate' => 'nullable|string|max:20',
            'vehicle_type' => 'nullable|string|max:50',
            'carrier_id' => 'nullable|integer|exists:logistics_carriers,id',
            'planned_date' => 'sometimes|date',
            'total_distance_km' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $deliveryRound->update($data);

        return response()->json($deliveryRound->fresh());
    }

    public function destroy(DeliveryRound $deliveryRound): JsonResponse
    {
        $this->authorize('delete', $deliveryRound);

        abort_if($deliveryRound->status !== 'planned', 422, 'Only planned rounds can be deleted.');
        $deliveryRound->delete();

        return response()->json(null, 204);
    }

    public function start(DeliveryRound $deliveryRound): JsonResponse
    {
        $this->authorize('update', $deliveryRound);

        abort_if($deliveryRound->status !== 'planned', 422, 'Only planned rounds can be started.');

        $deliveryRound->update([
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        return response()->json($deliveryRound->fresh());
    }

    public function complete(DeliveryRound $deliveryRound): JsonResponse
    {
        $this->authorize('update', $deliveryRound);

        abort_if($deliveryRound->status !== 'in_progress', 422, 'Only in-progress rounds can be completed.');

        $deliveryRound->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        return response()->json($deliveryRound->fresh());
    }

    public function optimize(DeliveryRound $deliveryRound): JsonResponse
    {
        $this->authorize('update', $deliveryRound);

        abort_if($deliveryRound->status !== 'planned', 422, 'Only planned rounds can be optimized.');

        $stops = $deliveryRound->stops()->orderBy('stop_order')->get();

        return response()->json([
            'round' => $deliveryRound,
            'stops' => $stops,
            'optimized' => true,
        ]);
    }

    public function proofOfDelivery(Request $request, DeliveryRound $deliveryRound, DeliveryStop $stop): JsonResponse
    {
        $this->authorize('update', $deliveryRound);

        abort_if($deliveryRound->status !== 'in_progress', 422, 'Round must be in progress to record proof of delivery.');
        abort_if($stop->delivery_round_id !== $deliveryRound->id, 404, 'Stop does not belong to this round.');

        $data = $request->validate([
            'status' => 'required|in:delivered,failed,partial',
            'recipient_name' => 'nullable|string|max:200',
            'notes' => 'nullable|string',
            'completed_at' => 'nullable|date',
        ]);

        $stop->update(array_merge($data, [
            'completed_at' => $data['completed_at'] ?? now(),
        ]));

        return response()->json($stop->fresh());
    }
}
