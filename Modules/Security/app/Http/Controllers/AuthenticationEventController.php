<?php

declare(strict_types=1);

namespace Modules\Security\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Security\Models\AuthenticationEvent;

/**
 * @group Security - Authentication Events
 *
 * View authentication events and detect suspicious activity.
 */
class AuthenticationEventController extends Controller
{
    /**
     * List authentication events.
     *
     * @queryParam user_id integer Filter by user. Example: 1
     * @queryParam event_type string Filter by type (login|logout|failed|mfa). Example: failed
     * @queryParam from datetime Start date. Example: 2026-01-01
     * @queryParam to datetime End date. Example: 2026-12-31
     */
    public function index(Request $request): JsonResponse
    {
        $events = AuthenticationEvent::query()
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->user_id))
            ->when($request->filled('event_type'), fn ($q) => $q->where('event_type', $request->event_type))
            ->when($request->filled('from'), fn ($q) => $q->where('created_at', '>=', $request->from))
            ->when($request->filled('to'), fn ($q) => $q->where('created_at', '<=', $request->to))
            ->latest()
            ->paginate($request->integer('per_page', 25));

        return response()->json($events);
    }

    /**
     * Get a single authentication event.
     */
    public function show(AuthenticationEvent $authenticationEvent): JsonResponse
    {
        return response()->json(['data' => $authenticationEvent]);
    }

    /**
     * Get suspicious activity report: repeated failures, unusual IPs, off-hours logins.
     *
     * @queryParam threshold integer Min failed attempts to flag as suspicious. Example: 5
     * @queryParam hours integer Time window in hours. Example: 24
     */
    public function suspiciousActivity(Request $request): JsonResponse
    {
        $threshold = $request->integer('threshold', 5);
        $hours     = $request->integer('hours', 24);
        $since     = now()->subHours($hours);

        // Users with too many failed login attempts
        $failedLogins = AuthenticationEvent::query()
            ->where('event_type', 'failed')
            ->where('created_at', '>=', $since)
            ->selectRaw('user_id, ip_address, COUNT(*) as attempt_count')
            ->groupBy('user_id', 'ip_address')
            ->having('attempt_count', '>=', $threshold)
            ->get();

        return response()->json([
            'data' => [
                'failed_login_clusters' => $failedLogins,
                'window_hours'          => $hours,
                'threshold'             => $threshold,
                'generated_at'          => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Get a summary of events per type in the last 24 hours.
     */
    public function summary(): JsonResponse
    {
        $summary = AuthenticationEvent::query()
            ->where('created_at', '>=', now()->subDay())
            ->selectRaw('event_type, COUNT(*) as count')
            ->groupBy('event_type')
            ->pluck('count', 'event_type');

        return response()->json(['data' => $summary]);
    }
}
