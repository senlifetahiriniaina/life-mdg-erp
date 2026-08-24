<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Models\SessionEnhanced;
use Modules\Core\Services\SessionManagementDashboard;

/**
 * Chantier 38.1: self-service "my sessions" endpoints activating the
 * real, previously-orphaned SessionManagementDashboard (zero controller/route
 * consumer before this — confirmed via grep across the whole app).
 *
 * Important, empirically-confirmed caveat (not glossed over): SessionEnhanced
 * rows are only ever written by App\Http\Middleware\SanctumSessionSecurity
 * (the `session.security` middleware alias), which explicitly no-ops whenever
 * $user->currentAccessToken() is a Laravel\Sanctum\TransientToken instance.
 * Confirmed via a real tinker session (Auth::guard('web')->login() +
 * Auth::guard('sanctum')->user()->currentAccessToken()) that this is exactly
 * what Sanctum's stateful-SPA guard resolution produces for every real
 * Inertia/session-cookie login this app's primary AuthenticatedSessionController
 * flow uses — so for the overwhelming majority of real users, these endpoints
 * will correctly return an empty session list, not an error. Only the
 * separate token-based Modules\Core\Http\Controllers\Api\AuthController::login()/
 * register() flow (which calls $user->createToken() explicitly) produces a
 * real PersonalAccessToken this dashboard has anything to show. Built anyway,
 * for correctness/completeness and because that second flow is real — but the
 * gap is real too and is called out here rather than silently assumed fixed.
 */
class SessionManagementController extends Controller
{
    public function __construct(private readonly SessionManagementDashboard $dashboard)
    {
    }

    /** GET v1/sessions — the caller's own active sessions. */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'sessions' => $this->dashboard->getActiveSessions($user->id, $this->tenantId($user)),
            'summary' => $this->dashboard->getSessionSummary($user->id, $this->tenantId($user)),
            'current_session_id' => $this->currentSessionId($request),
        ]);
    }

    /** GET v1/sessions/{id} — detail of one of the caller's own sessions. */
    public function show(Request $request, string $id): JsonResponse
    {
        $session = SessionEnhanced::find($id);

        if (!$session || (string) $session->user_id !== (string) $request->user()->id) {
            return response()->json(['message' => 'Session not found.'], 404);
        }

        return response()->json(['session' => $this->dashboard->getSessionDetails($id)]);
    }

    /** GET v1/sessions/{id}/timeline — activity history of one of the caller's own sessions. */
    public function timeline(Request $request, string $id): JsonResponse
    {
        $session = SessionEnhanced::find($id);

        if (!$session || (string) $session->user_id !== (string) $request->user()->id) {
            return response()->json(['message' => 'Session not found.'], 404);
        }

        return response()->json(['events' => $this->dashboard->getSessionActivityTimeline($id)]);
    }

    /** DELETE v1/sessions/{id} — end one of the caller's own sessions remotely. */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $result = $this->dashboard->terminateSession($id, $request->user()->id);

        return response()->json($result, $result['success'] ? 200 : 403);
    }

    /** POST v1/sessions/terminate-others — end every session except the current one. */
    public function terminateOthers(Request $request): JsonResponse
    {
        $currentSessionId = $this->currentSessionId($request);

        if ($currentSessionId === null) {
            // A TransientToken-backed (real Inertia) request has no tracked
            // "current session" row to preserve — see class docblock. Rather
            // than guess or silently no-op, tell the caller plainly.
            return response()->json([
                'success' => false,
                'message' => 'This session type is not tracked for remote termination.',
                'terminated_count' => 0,
            ], 422);
        }

        $user = $request->user();
        $result = $this->dashboard->terminateAllOtherSessions($user->id, $currentSessionId, $this->tenantId($user));

        return response()->json($result);
    }

    private function currentSessionId(Request $request): ?string
    {
        $token = $request->user()?->currentAccessToken();

        if (!$token || $token instanceof \Laravel\Sanctum\TransientToken) {
            return null;
        }

        return (string) $token->id;
    }

    private function tenantId($user): ?string
    {
        $companyId = $user->company_id ?? null;

        return $companyId !== null ? (string) $companyId : null;
    }
}
