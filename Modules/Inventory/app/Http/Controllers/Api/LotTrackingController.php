<?php

declare(strict_types=1);

namespace Modules\Inventory\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Inventory\Models\Lot;
use Modules\Inventory\Services\LotTrackingService;

/**
 * @group Controllers - Lot Tracking
 *
 * Manage Lot Tracking resources.
 */
class LotTrackingController extends Controller
{
    public function __construct(private readonly LotTrackingService $service) {}

    public function index(Request $request): JsonResponse
    {
        $query = Lot::query();

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->integer('product_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return response()->json($query->paginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lot_number' => 'required|string|max:100|unique:inventory_lots,lot_number',
            'product_id' => 'nullable|integer|exists:inventory_products,id',
            'serial_number' => 'nullable|string|max:100',
            'manufacture_date' => 'nullable|date',
            'expiry_date' => 'nullable|date',
            'quantity' => 'nullable|numeric|min:0',
            'status' => 'nullable|in:active,expired,quarantine,depleted',
            'warehouse_id' => 'nullable|integer',
            'notes' => 'nullable|string',
        ]);

        $lot = $this->service->createLot($data);

        return response()->json($lot, 201);
    }

    public function expiring(Request $request): JsonResponse
    {
        $days = $request->integer('days', 30);
        $lots = $this->service->getExpiringLots($days);

        return response()->json(['data' => $lots]);
    }

    public function show(Lot $lot): JsonResponse
    {
        return response()->json($lot->load('movements'));
    }

    public function update(Request $request, Lot $lot): JsonResponse
    {
        $data = $request->validate([
            'lot_number' => 'sometimes|string|max:100|unique:inventory_lots,lot_number,'.$lot->id,
            'product_id' => 'nullable|integer|exists:inventory_products,id',
            'serial_number' => 'nullable|string|max:100',
            'manufacture_date' => 'nullable|date',
            'expiry_date' => 'nullable|date',
            'quantity' => 'nullable|numeric|min:0',
            'status' => 'nullable|in:active,expired,quarantine,depleted',
            'warehouse_id' => 'nullable|integer',
            'notes' => 'nullable|string',
        ]);

        $lot->update($data);

        return response()->json($lot->fresh());
    }

    public function destroy(Lot $lot): JsonResponse
    {
        $lot->delete();

        return response()->json(null, 204);
    }

    public function receive(Request $request, Lot $lot): JsonResponse
    {
        $data = $request->validate([
            'qty' => 'required|numeric|min:0.0001',
            'reference' => 'nullable|string|max:100',
        ]);

        $movement = $this->service->receiveLot($lot, (float) $data['qty'], (string) ($data['reference'] ?? ''));

        return response()->json($movement, 201);
    }

    public function issue(Request $request, Lot $lot): JsonResponse
    {
        $data = $request->validate([
            'qty' => 'required|numeric|min:0.0001',
            'reference' => 'nullable|string|max:100',
        ]);

        try {
            $movement = $this->service->issueLot($lot, (float) $data['qty'], (string) ($data['reference'] ?? ''));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($movement, 201);
    }

    public function transfer(Request $request, Lot $lot): JsonResponse
    {
        $data = $request->validate([
            'from_warehouse_id' => 'required|integer',
            'to_warehouse_id' => 'required|integer',
            'qty' => 'required|numeric|min:0.0001',
        ]);

        $movement = $this->service->transferLot(
            $lot,
            (int) $data['from_warehouse_id'],
            (int) $data['to_warehouse_id'],
            (float) $data['qty']
        );

        return response()->json($movement, 201);
    }

    public function quarantine(Request $request, Lot $lot): JsonResponse
    {
        $data = $request->validate([
            'reason' => 'nullable|string',
        ]);

        $this->service->quarantineLot($lot, (string) ($data['reason'] ?? ''));

        return response()->json(['message' => 'Lot quarantined successfully.']);
    }

    public function movements(Lot $lot): JsonResponse
    {
        return response()->json($lot->movements()->paginate(20));
    }

    public function stats(int $productId): JsonResponse
    {
        return response()->json($this->service->getLotStats($productId));
    }
}
