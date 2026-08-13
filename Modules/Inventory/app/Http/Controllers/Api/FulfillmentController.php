<?php

declare(strict_types=1);

namespace Modules\Inventory\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Inventory\Services\ThirdPartyLogistics\FulfillmentService;

/**
 * @group Inventory - 3PL Fulfillment
 */
class FulfillmentController extends Controller
{
    public function __construct(private readonly FulfillmentService $service) {}

    /**
     * GET /inventory/3pl/connectors
     * List all registered 3PL connectors with sandbox status.
     */
    public function connectors(): JsonResponse
    {
        $available = $this->service->availableConnectors();

        $list = array_map(fn (string $name) => [
            'name'    => $name,
            'sandbox' => $this->service->getConnector($name)->isSandbox(),
        ], $available);

        return response()->json(['connectors' => $list]);
    }

    /**
     * POST /inventory/3pl/fulfill
     * Route a fulfillment order to a 3PL connector.
     */
    public function fulfill(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_id'      => 'required',
            'connector'     => 'required|string',
            'ship_to'       => 'nullable|array',
            'ship_to.name'    => 'nullable|string',
            'ship_to.address' => 'nullable|string',
            'ship_to.city'    => 'nullable|string',
            'ship_to.state'   => 'nullable|string',
            'ship_to.zip'     => 'nullable|string',
            'ship_to.country' => 'nullable|string|size:2',
            'shipping_method' => 'nullable|string',
            'items'         => 'required|array|min:1',
            'items.*.sku'      => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        try {
            $result = $this->service->routeFulfillment(
                $data['order_id'],
                $data['connector'],
                $data
            );
            return response()->json($result, 201);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * GET /inventory/3pl/orders/{referenceId}/status
     * Get fulfillment order status from 3PL.
     */
    public function orderStatus(Request $request, string $referenceId): JsonResponse
    {
        $connector = $request->query('connector', 'shipbob');

        try {
            $status = $this->service->trackFulfillment($referenceId, (string) $connector);
            return response()->json($status);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /inventory/3pl/sync-inventory
     * Sync inventory levels from a specific 3PL fulfiller.
     */
    public function syncInventory(Request $request): JsonResponse
    {
        $data = $request->validate([
            'connector' => 'required|string',
        ]);

        try {
            $levels = $this->service->syncInventoryFromFulfiller($data['connector']);
            return response()->json(['inventory' => $levels, 'count' => count($levels)]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}
