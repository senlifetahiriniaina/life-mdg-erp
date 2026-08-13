<?php

declare(strict_types=1);

namespace Modules\CRM\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\CRM\Services\ForecastService;

/**
 * @group CRM - AI Sales Forecasting
 */
class ForecastController extends Controller
{
    public function __construct(private readonly ForecastService $forecastService) {}

    /**
     * List all forecasts.
     */
    public function index(Request $request): JsonResponse
    {
        $isManager = $request->user()->hasAnyRole(['super-admin', 'admin', 'manager']);

        if ($isManager && $request->filled('user_id')) {
            $userId = (int) $request->user_id;
        } elseif ($isManager) {
            // Managers see all forecasts when no user_id filter is applied
            $userId = null;
        } else {
            $userId = $request->user()->id;
        }

        $forecasts = $this->forecastService->allForecasts($userId);

        return response()->json(['data' => $forecasts]);
    }

    /**
     * Generate (or regenerate) a forecast for a period.
     */
    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period' => 'required|string|max:20',
            'user_id' => 'nullable|exists:users,id',
        ]);

        $isManager = $request->user()->hasAnyRole(['super-admin', 'admin', 'manager']);
        $userId = $isManager && isset($validated['user_id'])
            ? (int) $validated['user_id']
            : $request->user()->id;

        $forecast = $this->forecastService->generateForecast($validated['period'], $userId);

        return response()->json($forecast, 201);
    }
}
