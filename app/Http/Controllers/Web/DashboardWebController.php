<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\AI\AiDashboardService;
use App\Services\DashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardWebController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboardService,
        private readonly AiDashboardService $aiDashboardService,
    ) {}

    /**
     * Render the 360° dashboard Inertia page with pre-loaded metrics and insights.
     */
    public function index(Request $request): Response
    {
        /** @var \App\Models\User $user */
        $user    = $request->user();
        $metrics = $this->dashboardService->getMetricsForRole($user);
        $insights = $this->aiDashboardService->generateInsights($user, $metrics);

        return Inertia::render('Dashboard/Index', [
            'metrics'  => $metrics,
            'insights' => $insights,
        ]);
    }
}
