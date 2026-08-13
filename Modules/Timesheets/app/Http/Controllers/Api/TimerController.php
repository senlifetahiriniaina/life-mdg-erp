<?php

declare(strict_types=1);

namespace Modules\Timesheets\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Timesheets\Services\TimerService;

class TimerController extends Controller
{
    public function __construct(private TimerService $timerService) {}

    /**
     * POST /api/v1/timesheets/timer/start
     */
    public function start(Request $request): JsonResponse
    {
        $data = $request->validate([
            'project_id'  => ['nullable', 'integer'],
            'task_id'     => ['nullable', 'integer'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $state = $this->timerService->start($request->user()->id, $data);
            return response()->json($state, 201);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/v1/timesheets/timer/stop
     */
    public function stop(Request $request): JsonResponse
    {
        try {
            $result = $this->timerService->stop($request->user()->id);
            return response()->json($result);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * GET /api/v1/timesheets/timer/current
     *
     * Returns the active timer state (for browser extension polling).
     */
    public function current(Request $request): JsonResponse
    {
        $state = $this->timerService->getActiveTimer($request->user()->id);

        if (!$state) {
            return response()->json(['running' => false, 'timer' => null]);
        }

        $started = new \DateTime($state['started_at']);
        $elapsed = (new \DateTime())->getTimestamp() - $started->getTimestamp();

        return response()->json([
            'running'          => true,
            'timer'            => $state,
            'elapsed_seconds'  => $elapsed,
        ]);
    }

    /**
     * DELETE /api/v1/timesheets/timer/discard
     *
     * Discard an active timer without saving an entry.
     */
    public function discard(Request $request): JsonResponse
    {
        $this->timerService->clear($request->user()->id);
        return response()->json(['message' => 'Timer discarded.']);
    }
}
