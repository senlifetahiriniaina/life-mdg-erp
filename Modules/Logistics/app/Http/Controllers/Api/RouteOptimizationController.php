<?php

declare(strict_types=1);

namespace Modules\Logistics\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Modules\Logistics\Services\RouteOptimizerService;

/**
 * @group Logistics - VRP Route Optimizer
 *
 * Vehicle Routing Problem solver with 2-opt improvement and time-window support.
 * For <= 20 stops: synchronous response.
 * For > 20 stops: returns a job_id; poll /result to fetch the solution.
 */
class RouteOptimizationController extends Controller
{
    public function __construct(private readonly RouteOptimizerService $optimizer) {}

    /**
     * Solve a VRP instance.
     *
     * POST /api/v1/logistics/routes/optimize
     */
    public function optimize(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'stops'                               => 'required|array|min:1|max:200',
            'stops.*.id'                          => 'required|string',
            'stops.*.lat'                         => 'required|numeric|between:-90,90',
            'stops.*.lng'                         => 'required|numeric|between:-180,180',
            'stops.*.time_window_open'            => 'nullable|string|regex:/^\d{2}:\d{2}$/',
            'stops.*.time_window_close'           => 'nullable|string|regex:/^\d{2}:\d{2}$/',
            'stops.*.service_time_minutes'        => 'nullable|integer|min:0|max:480',
            'stops.*.demand'                      => 'nullable|numeric|min:0',
            'vehicles'                            => 'required|array|min:1',
            'vehicles.*.id'                       => 'required|string',
            'vehicles.*.capacity'                 => 'required|numeric|min:0',
            'vehicles.*.start_lat'                => 'required|numeric|between:-90,90',
            'vehicles.*.start_lng'                => 'required|numeric|between:-180,180',
            'vehicles.*.max_stops'                => 'nullable|integer|min:1|max:200',
        ]);

        $stops    = $validated['stops'];
        $vehicles = $validated['vehicles'];

        // Synchronous path for small instances (<= 20 stops)
        if (count($stops) <= 20) {
            $result = $this->optimizer->solve($stops, $vehicles);

            return response()->json(['status' => 'completed', 'result' => $result]);
        }

        // Async path for larger instances: record a job, solve, store in cache
        $jobId = 'vrp_' . uniqid('', true);
        Cache::put("vrp_job_{$jobId}", ['status' => 'pending', 'stops' => count($stops)], 3600);

        // Run synchronously but respond with job-style envelope (simplified — no queue needed)
        $result = $this->optimizer->solve($stops, $vehicles);
        Cache::put("vrp_job_{$jobId}", ['status' => 'completed', 'result' => $result], 3600);

        return response()->json(['status' => 'queued', 'job_id' => $jobId], 202);
    }

    /**
     * Fetch result for a previously submitted VRP job.
     *
     * GET /api/v1/logistics/routes/optimize/{jobId}/result
     */
    public function result(string $jobId): JsonResponse
    {
        // Basic sanitise — only allow safe characters in job ID
        if (! preg_match('/^vrp_[a-zA-Z0-9_.]+$/', $jobId)) {
            return response()->json(['error' => 'Invalid job ID format'], 400);
        }

        $data = Cache::get("vrp_job_{$jobId}");

        if ($data === null) {
            return response()->json(['error' => 'Job not found or expired'], 404);
        }

        return response()->json($data);
    }
}
