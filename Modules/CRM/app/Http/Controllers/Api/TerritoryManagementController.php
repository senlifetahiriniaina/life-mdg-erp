<?php

declare(strict_types=1);

namespace Modules\CRM\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\CRM\Models\Territory;
use Modules\CRM\Services\TerritoryManagementService;

/**
 * @group Controllers - Territory Management
 *
 * Manage Territory Management resources.
 */
class TerritoryManagementController extends Controller
{
    public function __construct(private readonly TerritoryManagementService $territoryService) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Territory::class);

        $territories = Territory::query();

        if ($request->has('region')) {
            $territories->where('region', $request->region);
        }

        return response()->json(
            $territories->with('assignments.user')->paginate(15)
        );
    }

    public function autoBalance(Request $request)
    {
        $this->authorize('create', Territory::class);

        $request->validate([
            'strategy' => 'required|in:revenue,count,geography',
            'metric' => 'nullable|string',
        ]);

        $result = $this->territoryService->autoBalanceTerritories(
            $request->input('strategy'),
            $request->input('metric')
        );

        return response()->json([
            'balanced' => true,
            'rebalanced_count' => $result['count'],
            'affected_reps' => $result['reps'],
            'changes' => $result['changes'],
        ]);
    }

    public function coverage(Request $request)
    {
        $this->authorize('viewAny', Territory::class);

        $coverage = $this->territoryService->calculateCoverage();

        return response()->json([
            'total_territories' => $coverage['total'],
            'assigned_territories' => $coverage['assigned'],
            'unassigned_territories' => $coverage['unassigned'],
            'coverage_percentage' => $coverage['percentage'],
            'gaps' => $coverage['gaps'],
        ]);
    }

    public function quotaDistribution(Request $request)
    {
        $this->authorize('viewAny', Territory::class);

        $distribution = $this->territoryService->distributeQuotas(
            $request->input('total_quota', 0),
            $request->input('method', 'proportional')
        );

        return response()->json($distribution);
    }

    public function forecast(Request $request)
    {
        $this->authorize('viewAny', Territory::class);

        $territoryId = $request->input('territory_id');
        $forecast = $this->territoryService->forecastTerritorySales($territoryId);

        return response()->json($forecast);
    }

    public function atRisk(Request $request)
    {
        $this->authorize('viewAny', Territory::class);

        $atRisk = $this->territoryService->identifyAtRiskTerritories();

        return response()->json([
            'at_risk_count' => count($atRisk),
            'territories' => $atRisk,
            'recommendations' => $this->territoryService->getRiskMitigations($atRisk),
        ]);
    }
}
