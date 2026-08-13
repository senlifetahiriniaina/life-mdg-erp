<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AI\AiDashboardService;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboardService,
        private readonly AiDashboardService $aiDashboardService,
    ) {}

    /**
     * Return role-based dashboard metrics for the authenticated user.
     */
    public function metrics(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user    = $request->user();
        $metrics = $this->dashboardService->getMetricsForRole($user);

        return response()->json($metrics);
    }

    /**
     * Return 5 AI-generated insights + suggested actions for the authenticated user.
     *
     * @return JsonResponse
     */
    public function aiInsights(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user    = $request->user();
        $metrics = $this->dashboardService->getMetricsForRole($user);
        $insights = $this->aiDashboardService->generateInsights($user, $metrics);

        return response()->json($insights);
    }
}
