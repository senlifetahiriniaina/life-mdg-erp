<?php

declare(strict_types=1);

namespace Modules\CRM\Http\Controllers\Api;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\CRM\Services\PipelineAnalyticsService;

/**
 * @group Controllers - Pipeline Analytics
 *
 * Manage Pipeline Analytics resources.
 */
class PipelineAnalyticsController extends Controller
{
    public function __construct(private readonly PipelineAnalyticsService $analytics) {}

    // Chantier 32.15: every method here was fully tenant-unfiltered — see
    // PipelineAnalyticsService's own docblock for the full finding. Every call below now
    // threads the caller's own company_id through.

    public function dashboard(Request $request): JsonResponse
    {
        return response()->json($this->analytics->getDashboard($request->user()->company_id));
    }

    public function winRate(Request $request): JsonResponse
    {
        $from = $request->filled('from') ? Carbon::parse($request->input('from')) : null;
        $to = $request->filled('to') ? Carbon::parse($request->input('to')) : null;

        return response()->json([
            'win_rate' => $this->analytics->getWinRate($from, $to, $request->user()->company_id),
        ]);
    }

    public function recordWin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'opportunity_id' => ['required', 'integer'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $record = $this->analytics->recordWin((int) $validated['opportunity_id'], $validated, $request->user()->company_id);

        return response()->json($record, 201);
    }

    public function recordLoss(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'opportunity_id' => ['required', 'integer'],
            'reason' => ['nullable', 'string', 'max:255'],
            'competitor' => ['nullable', 'string', 'max:255'],
        ]);

        $record = $this->analytics->recordLoss((int) $validated['opportunity_id'], $validated, $request->user()->company_id);

        return response()->json($record, 201);
    }

    public function conversionFunnel(Request $request): JsonResponse
    {
        $pipelineId = (int) $request->input('pipeline_id', 0);

        return response()->json($this->analytics->getConversionFunnel($pipelineId, $request->user()->company_id));
    }

    public function salesVelocity(Request $request): JsonResponse
    {
        $from = $request->filled('from') ? Carbon::parse($request->input('from')) : null;
        $to = $request->filled('to') ? Carbon::parse($request->input('to')) : null;

        return response()->json([
            'sales_velocity' => $this->analytics->getSalesVelocity($from, $to, $request->user()->company_id),
        ]);
    }

    public function stageDistribution(Request $request): JsonResponse
    {
        $pipelineId = (int) $request->input('pipeline_id', 0);

        return response()->json($this->analytics->getStageDistribution($pipelineId, $request->user()->company_id));
    }

    public function takeSnapshot(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pipeline_id' => ['required', 'integer'],
        ]);

        $snapshot = $this->analytics->takeSnapshot((int) $validated['pipeline_id'], $request->user()->company_id);

        return response()->json($snapshot, 201);
    }

    public function pipelineTrend(Request $request): JsonResponse
    {
        $pipelineId = (int) $request->input('pipeline_id', 0);
        $days = (int) $request->input('days', 30);

        return response()->json($this->analytics->getPipelineTrend($pipelineId, $days, $request->user()->company_id));
    }

    public function topPerformers(Request $request): JsonResponse
    {
        $limit = (int) $request->input('limit', 5);

        return response()->json($this->analytics->getTopPerformers($limit, $request->user()->company_id));
    }

    public function winLossReasons(Request $request): JsonResponse
    {
        $outcome = $request->input('outcome', 'lost');

        return response()->json($this->analytics->getWinLossReasons($outcome, $request->user()->company_id));
    }

    public function avgSalesCycle(Request $request): JsonResponse
    {
        return response()->json([
            'avg_sales_cycle_days' => $this->analytics->getAvgSalesCycle($request->user()->company_id),
        ]);
    }
}
