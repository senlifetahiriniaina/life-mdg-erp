<?php

declare(strict_types=1);

namespace Modules\Logistics\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Logistics\Services\LogisticsAnalyticsService;

/**
 * @group Logistics - Analytics
 */
class LogisticsAnalyticsController extends Controller
{
    public function __construct(private readonly LogisticsAnalyticsService $service) {}

    public function kpis(): JsonResponse
    {
        return response()->json($this->service->kpis());
    }

    public function carrierPerformance(): JsonResponse
    {
        return response()->json(['data' => $this->service->carrierPerformance()]);
    }

    public function shipmentStats(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        return response()->json($this->service->shipmentStats($filters));
    }

    public function co2Emissions(): JsonResponse
    {
        return response()->json($this->service->co2Emissions());
    }
}
