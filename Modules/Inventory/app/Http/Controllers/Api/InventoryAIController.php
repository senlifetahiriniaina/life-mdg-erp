<?php

declare(strict_types=1);

namespace Modules\Inventory\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Inventory\Services\AI\InventoryAIService;

/**
 * @group Inventory - AI
 *
 * AI-powered inventory forecasting and reorder suggestions.
 */
class InventoryAIController extends Controller
{
    public function __construct(private readonly InventoryAIService $ai) {}

    public function forecastDemand(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product' => 'required|array',
            'movements' => 'required|array',
        ]);

        return response()->json($this->ai->forecastDemand($data['product'], $data['movements']));
    }

    public function suggestReorder(Request $request): JsonResponse
    {
        $data = $request->validate(['stock' => 'required|array']);

        return response()->json($this->ai->suggestReorder($data['stock']));
    }

    public function analyzeAnomalies(Request $request): JsonResponse
    {
        $data = $request->validate(['movements' => 'required|array|max:200']);

        return response()->json(['analysis' => $this->ai->analyzeAnomalies($data['movements'])]);
    }

    public function classifyABC(Request $request): JsonResponse
    {
        $data = $request->validate(['products' => 'required|array|min:1']);

        return response()->json($this->ai->classifyProductsABC($data['products']));
    }

    public function detectObsolete(Request $request): JsonResponse
    {
        $data = $request->validate(['products' => 'required|array|min:1']);

        return response()->json($this->ai->detectObsoleteProducts($data['products']));
    }
}
