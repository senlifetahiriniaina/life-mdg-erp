<?php

declare(strict_types=1);

namespace Modules\HR\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\HR\Services\HrDashboardService;

/**
 * @group HR - Dashboard
 */
class HrDashboardController extends Controller
{
    public function __construct(private readonly HrDashboardService $service) {}

    /**
     * Full dashboard — all stats in a single response.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'stats' => $this->service->getStats(),
            'department_distribution' => $this->service->getDepartmentDistribution(),
            'leave_stats' => $this->service->getLeaveStats(),
            'recruitment_funnel' => $this->service->getRecruitmentFunnel(),
            'attendance' => $this->service->getAttendanceToday(),
        ]);
    }

    /**
     * Lightweight realtime stats for polling (headcount, absences, attendance).
     */
    public function realtime(): JsonResponse
    {
        $stats = $this->service->getStats();
        $attendance = $this->service->getAttendanceToday();

        return response()->json([
            'headcount' => $stats['headcount'],
            'absences_today' => $stats['absences_today'],
            'attendance' => $attendance,
        ]);
    }
}
