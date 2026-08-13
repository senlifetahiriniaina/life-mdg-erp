<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @group Controllers - Realtime
 *
 * Manage Realtime resources.
 */
class RealtimeController
{
    public function subscribe(Request $request): StreamedResponse
    {
        $user = Auth::user();
        $tenantId = $request->integer('tenant_id');
        $userId = $request->integer('user_id');

        if ($user->id !== $userId || $user->tenant_id !== $tenantId) {
            return response()->stream(function () {
                echo "event: error\n";
                echo 'data: '.json_encode(['message' => 'Unauthorized'])."\n\n";
            }, 200, [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'Connection' => 'keep-alive',
                'X-Accel-Buffering' => 'no',
            ]);
        }

        return response()->stream(function () {
            // Send a comment to keep the connection alive
            echo ": heartbeat\n\n";

            $lastActivity = now();

            // Simulate real-time updates for demo
            // In production, this would listen to actual Redis pub/sub or queue events
            while (true) {
                if (connection_aborted()) {
                    break;
                }

                // Heartbeat every 30 seconds
                if (now()->diffInSeconds($lastActivity) >= 30) {
                    echo ": heartbeat\n\n";
                    flush();
                    $lastActivity = now();
                }

                usleep(100000); // Sleep 100ms
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function health(Request $request): array
    {
        return [
            'status' => 'ok',
            'broadcaster' => config('broadcasting.default'),
            'reverb_enabled' => config('broadcasting.default') === 'reverb',
            'echo_configured' => env('VITE_REVERB_APP_KEY') ? true : false,
        ];
    }
}
