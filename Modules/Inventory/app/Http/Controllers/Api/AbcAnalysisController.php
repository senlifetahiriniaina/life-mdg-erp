<?php

declare(strict_types=1);

namespace Modules\Inventory\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Inventory\Services\AI\ABCAnalysisService;

/**
 * @group Inventory - ABC Analysis
 *
 * Chantier 32.22 (14-layer deep audit, layer 9 — fake/dead): this real,
 * fully-written, deterministic Pareto-principle ABC classification engine
 * (EOQ/reorder-point/safety-stock formulas, not an LLM guess like
 * `InventoryAIController::classifyABC()`) had zero controller/route
 * consumer anywhere — confirmed empirically via grep, only its own
 * isolated test file ever called it. Classified "à activer" rather than
 * deleted: the `value`-metric path is real and already tested/working;
 * `analyzeVelocity()`/`getAnnualDemand()` referenced a
 * `Modules\Inventory\Models\InventoryMovement` class that has never
 * existed anywhere in this repo (a guaranteed fatal error the moment
 * either was called with real data) — fixed to the real `StockMovement`
 * model, which carries the exact same fields the broken code assumed.
 */
class AbcAnalysisController extends Controller
{
    public function __construct(private readonly ABCAnalysisService $service) {}

    public function analyze(Request $request): JsonResponse
    {
        $data = $request->validate([
            'metric' => 'nullable|in:value,quantity,turnover,demand',
            'warehouse_id' => 'nullable|integer',
        ]);

        return response()->json(
            $this->service->analyzeInventory($data['metric'] ?? 'value', $data['warehouse_id'] ?? null)
        );
    }

    public function velocity(Request $request): JsonResponse
    {
        $data = $request->validate([
            'warehouse_id' => 'nullable|integer',
            'days_back' => 'nullable|integer|min:1|max:365',
        ]);

        return response()->json(
            $this->service->analyzeVelocity($data['warehouse_id'] ?? null, $data['days_back'] ?? 90)
        );
    }
}
