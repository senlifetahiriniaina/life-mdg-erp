<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Api;

use Illuminate\Http\Request;

/**
 * @group Controllers - Realtime
 *
 * Chantier 32.1: this used to also expose subscribe() — a Server-Sent-
 * Events endpoint whose own code comments admitted it was a "Simulate
 * real-time updates for demo" ("In production, this would listen to actual
 * Redis pub/sub or queue events") — an infinite while(true) loop that only
 * ever sent heartbeats, never a single real event. Confirmed dead (zero
 * frontend caller anywhere, zero test) AND, independently, broken even if
 * called: its own authorization check compared the phantom, never-
 * populated users.tenant_id column against a client-supplied tenant_id
 * (null !== (int) anything is always true in PHP), so it unconditionally
 * returned "Unauthorized" to every real caller regardless of input. Real,
 * working real-time delivery in this app already goes through Laravel
 * Reverb + Echo (see CLAUDE.md's Chantier 20/27 entries — NotificationBell,
 * Messaging, private-channel broadcasting) — this SSE endpoint was a dead,
 * non-functional parallel mechanism, deleted rather than fixed. health()
 * is real, harmless, and kept as-is.
 */
class RealtimeController
{
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
