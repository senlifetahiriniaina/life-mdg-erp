<?php

declare(strict_types=1);

namespace Modules\Integration\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Integration\Services\FirebaseService;
use Modules\Integration\Services\SupabaseService;

/**
 * Handles status and test endpoints for Supabase and Firebase integrations.
 */
class BackendStatusController extends Controller
{
    public function __construct(
        private readonly SupabaseService $supabaseService,
        private readonly FirebaseService $firebaseService,
    ) {}

    // -------------------------------------------------------------------------
    // Supabase
    // -------------------------------------------------------------------------

    /**
     * GET /api/v1/integration/supabase/status
     * Check whether Supabase is configured and reachable.
     */
    public function supabaseStatus(): JsonResponse
    {
        $configured = $this->supabaseService->isConfigured();

        if (! $configured) {
            return response()->json([
                'configured' => false,
                'reachable'  => false,
                'message'    => 'SUPABASE_URL and SUPABASE_SERVICE_ROLE_KEY are not set.',
            ]);
        }

        // Probe the REST root to verify network reachability.
        $result = $this->supabaseService->from('_health_check_nonexistent_' . uniqid());
        $reachable = $result['error'] === null || str_contains((string) $result['error'], '404') || str_contains((string) $result['error'], 'Not Found');

        return response()->json([
            'configured' => true,
            'reachable'  => $reachable,
            'message'    => $reachable ? 'Supabase is configured and reachable.' : 'Supabase is configured but unreachable: ' . $result['error'],
            'realtime'   => $this->supabaseService->getRealtimeConfig(),
        ]);
    }

    /**
     * POST /api/v1/integration/supabase/test
     * Run a test PostgREST query against a table provided in the request body.
     */
    public function supabaseTest(Request $request): JsonResponse
    {
        $request->validate([
            'table' => ['required', 'string', 'regex:/^[a-zA-Z0-9_]+$/'],
        ]);

        if (! $this->supabaseService->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'Supabase is not configured.',
            ], 422);
        }

        $result = $this->supabaseService->from($request->input('table'));

        return response()->json([
            'success' => $result['error'] === null,
            'data'    => $result['data'],
            'error'   => $result['error'],
        ]);
    }

    // -------------------------------------------------------------------------
    // Firebase
    // -------------------------------------------------------------------------

    /**
     * GET /api/v1/integration/firebase/status
     * Check whether Firebase is configured.
     */
    public function firebaseStatus(): JsonResponse
    {
        $configured = $this->firebaseService->isConfigured();

        return response()->json([
            'configured' => $configured,
            'message'    => $configured
                ? 'Firebase is configured (project: ' . config('firebase.project_id') . ').'
                : 'FIREBASE_PROJECT_ID is not set.',
            'features'   => config('firebase.use_for', []),
        ]);
    }

    /**
     * POST /api/v1/integration/firebase/test-push
     * Send a test FCM push notification to a device token.
     */
    public function firebaseTestPush(Request $request): JsonResponse
    {
        $request->validate([
            'device_token' => ['required', 'string'],
            'title'        => ['sometimes', 'string', 'max:100'],
            'body'         => ['sometimes', 'string', 'max:500'],
        ]);

        if (! $this->firebaseService->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'Firebase is not configured.',
            ], 422);
        }

        $success = $this->firebaseService->sendPushNotification(
            $request->input('device_token'),
            $request->input('title', 'WideHalo ERP — Test'),
            $request->input('body', 'This is a test notification from WideHalo ERP.'),
        );

        return response()->json([
            'success' => $success,
            'message' => $success ? 'Push notification delivered.' : 'Delivery failed — check logs for details.',
        ]);
    }
}
