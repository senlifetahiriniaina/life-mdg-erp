<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Accounting\Models\FixedAsset;
use Modules\Accounting\Services\FixedAssetService;

/**
 * @group Accounting - Fixed Assets
 *
 * Fixed asset register, depreciation, and disposal management.
 */
class FixedAssetController extends Controller
{
    public function __construct(private readonly FixedAssetService $service) {}

    public function index(Request $request): JsonResponse
    {
        $assets = FixedAsset::query()
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->asset_class, fn ($q, $v) => $q->where('asset_class', $v))
            ->paginate(20);

        return response()->json($assets);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'asset_class' => 'required|in:building,machinery,vehicle,equipment,furniture,intangible,other',
            'acquisition_date' => 'required|date',
            'acquisition_cost' => 'required|numeric|min:0.01',
            'salvage_value' => 'nullable|numeric|min:0',
            'useful_life_years' => 'required|integer|min:1',
            'depreciation_method' => 'required|in:straight-line,declining-balance,units-of-production',
            'asset_account_id' => 'required|exists:acc_chart_of_accounts,id',
            'depreciation_expense_account_id' => 'required|exists:acc_chart_of_accounts,id',
            'accumulated_depreciation_account_id' => 'required|exists:acc_chart_of_accounts,id',
        ]);

        $validated['tenant_id'] = auth()->user()->tenant_id;

        $asset = $this->service->create($validated);

        return response()->json($asset, 201);
    }

    public function show(FixedAsset $fixedAsset): JsonResponse
    {
        return response()->json(
            $fixedAsset->load(['depreciations', 'disposal'])
        );
    }

    public function update(Request $request, FixedAsset $fixedAsset): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'asset_class' => 'sometimes|in:building,machinery,vehicle,equipment,furniture,intangible,other',
            'salvage_value' => 'sometimes|numeric|min:0',
            'useful_life_years' => 'sometimes|integer|min:1',
        ]);

        $fixedAsset->update($validated);

        return response()->json($fixedAsset);
    }

    public function destroy(FixedAsset $fixedAsset): JsonResponse
    {
        $fixedAsset->delete();

        return response()->json(['message' => 'Asset deleted']);
    }

    // ─── Depreciation ────────────────────────────────────────────────────────

    public function schedule(FixedAsset $fixedAsset): JsonResponse
    {
        $schedule = $this->service->depreciationSchedule($fixedAsset);

        return response()->json([
            'asset_id' => $fixedAsset->id,
            'asset_code' => $fixedAsset->asset_code,
            'acquisition_cost' => (float) $fixedAsset->acquisition_cost,
            'salvage_value' => (float) $fixedAsset->salvage_value,
            'depreciation_method' => $fixedAsset->depreciation_method,
            'schedule' => $schedule,
            'total_periods' => count($schedule),
        ]);
    }

    public function depreciate(Request $request, FixedAsset $fixedAsset): JsonResponse
    {
        $period = $request->input('period')
            ? Carbon::parse($request->input('period'))
            : now();

        $entry = $this->service->depreciate($fixedAsset, $period);

        if (! $entry) {
            return response()->json([
                'message' => 'Asset is already fully depreciated or period already recorded',
            ], 422);
        }

        return response()->json($entry, 201);
    }

    public function depreciateAll(Request $request): JsonResponse
    {
        $period = $request->input('period') ? Carbon::parse($request->input('period')) : now();
        $results = $this->service->depreciateAll($period);

        return response()->json($results);
    }

    // ─── Disposal ────────────────────────────────────────────────────────────

    public function dispose(Request $request, FixedAsset $fixedAsset): JsonResponse
    {
        if ($fixedAsset->status === 'disposed') {
            return response()->json(['message' => 'Asset is already disposed'], 422);
        }

        $validated = $request->validate([
            'disposal_date' => 'required|date',
            'disposal_type' => 'required|in:sale,write_off,trade_in,donation',
            'disposal_proceeds' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $disposal = $this->service->dispose(
            $fixedAsset,
            $validated['disposal_type'],
            (float) ($validated['disposal_proceeds'] ?? 0),
            Carbon::parse($validated['disposal_date']),
            $validated['notes'] ?? null
        );

        return response()->json($disposal, 201);
    }

    // ─── Asset Register ───────────────────────────────────────────────────────

    public function register(Request $request): JsonResponse
    {
        $register = $this->service->register($request->only(['status', 'asset_class']));

        return response()->json([
            'data' => $register,
            'totals' => [
                'acquisition_cost' => round($register->sum('acquisition_cost'), 2),
                'accumulated_depreciation' => round($register->sum('accumulated_depreciation'), 2),
                'net_book_value' => round($register->sum('net_book_value'), 2),
            ],
            'count' => $register->count(),
        ]);
    }
}
