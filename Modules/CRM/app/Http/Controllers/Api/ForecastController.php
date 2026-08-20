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
     *
     * Chantier "CRM tenant-isolation follow-up": a manager with no user_id filter previously
     * got allForecasts(null), a fully tenant-unfiltered query — every company's "manager view"
     * forecasts, confirmed empirically via a real cross-company HTTP request before this fix.
     * Now always scoped by the acting user's own company_id, regardless of role.
     */
    public function index(Request $request): JsonResponse
    {
        $isManager = $request->user()->hasAnyRole(['super-admin', 'admin', 'manager']);

        if ($isManager && $request->filled('user_id')) {
            $userId = (int) $request->user_id;
        } elseif ($isManager) {
            // Managers see all of their own company's forecasts when no user_id filter applied
            $userId = null;
        } else {
            $userId = $request->user()->id;
        }

        $forecasts = $this->forecastService->allForecasts($userId, $request->user()->company_id);

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

        $forecast = $this->forecastService->generateForecast($validated['period'], $userId, $request->user()->company_id);

        return response()->json($forecast, 201);
    }
}
