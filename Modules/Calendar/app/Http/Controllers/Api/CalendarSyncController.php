<?php

declare(strict_types=1);

namespace Modules\Calendar\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Calendar\Jobs\SyncCalendarJob;
use Modules\Calendar\Models\CalendarSyncToken;
use Modules\Calendar\Services\AppleCalendarService;
use Modules\Calendar\Services\GoogleCalendarService;
use Modules\Calendar\Services\OutlookCalendarService;
use Symfony\Component\HttpFoundation\Response;

class CalendarSyncController extends Controller
{
    public function __construct(
        private readonly GoogleCalendarService $googleService,
        private readonly OutlookCalendarService $outlookService,
        private readonly AppleCalendarService $appleService,
    ) {}

    // -----------------------------------------------------------------------
    // Google Calendar
    // -----------------------------------------------------------------------

    /**
     * GET /api/v1/calendar/sync/google/auth
     * Return the Google OAuth2 consent URL.
     */
    public function googleAuth(Request $request): JsonResponse
    {
        $url = $this->googleService->getAuthUrl($request->user()->id);

        return response()->json(['url' => $url]);
    }

    /**
     * GET /api/v1/calendar/sync/google/callback
     * Handle the OAuth2 callback code from Google.
     */
    public function googleCallback(Request $request): JsonResponse
    {
        $request->validate(['code' => 'required|string', 'state' => 'nullable|string']);

        $userId = $request->user()->id;

        try {
            $token = $this->googleService->handleCallback($userId, $request->input('code'));

            return response()->json([
                'connected' => true,
                'provider'  => 'google',
                'message'   => 'Google Calendar connecté avec succès.',
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'OAuth failed: ' . $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * POST /api/v1/calendar/sync/google/sync
     * Trigger a full Google Calendar sync for the current user.
     */
    public function googleSync(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        SyncCalendarJob::dispatch($userId, 'google');

        return response()->json(['message' => 'Synchronisation Google lancée.', 'queued' => true]);
    }

    /**
     * DELETE /api/v1/calendar/sync/google/disconnect
     * Revoke Google token and remove all synced Google events.
     */
    public function googleDisconnect(Request $request): JsonResponse
    {
        $this->googleService->disconnect($request->user()->id);

        return response()->json(['message' => 'Google Calendar déconnecté.']);
    }

    // -----------------------------------------------------------------------
    // Outlook Calendar
    // -----------------------------------------------------------------------

    /**
     * GET /api/v1/calendar/sync/outlook/auth
     */
    public function outlookAuth(Request $request): JsonResponse
    {
        $url = $this->outlookService->getAuthUrl($request->user()->id);

        return response()->json(['url' => $url]);
    }

    /**
     * GET /api/v1/calendar/sync/outlook/callback
     */
    public function outlookCallback(Request $request): JsonResponse
    {
        $request->validate(['code' => 'required|string']);

        $userId = $request->user()->id;

        try {
            $this->outlookService->handleCallback($userId, $request->input('code'));

            return response()->json([
                'connected' => true,
                'provider'  => 'outlook',
                'message'   => 'Microsoft Outlook connecté avec succès.',
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'OAuth failed: ' . $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * POST /api/v1/calendar/sync/outlook/sync
     */
    public function outlookSync(Request $request): JsonResponse
    {
        SyncCalendarJob::dispatch($request->user()->id, 'outlook');

        return response()->json(['message' => 'Synchronisation Outlook lancée.', 'queued' => true]);
    }

    /**
     * DELETE /api/v1/calendar/sync/outlook/disconnect
     */
    public function outlookDisconnect(Request $request): JsonResponse
    {
        $this->outlookService->disconnect($request->user()->id);

        return response()->json(['message' => 'Outlook Calendar déconnecté.']);
    }

    // -----------------------------------------------------------------------
    // Apple Calendar (iCloud CalDAV)
    // -----------------------------------------------------------------------

    /**
     * POST /api/v1/calendar/sync/apple/connect
     * Connect Apple Calendar via CalDAV credentials.
     */
    public function appleConnect(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'        => 'required|email',
            'app_password' => 'required|string|min:8',
            'server_url'   => 'nullable|url',
        ]);

        $userId = $request->user()->id;

        try {
            $this->appleService->connect(
                userId: $userId,
                email: $data['email'],
                appPassword: $data['app_password'],
                serverUrl: $data['server_url'] ?? null,
            );

            return response()->json([
                'connected' => true,
                'provider'  => 'apple',
                'message'   => 'Apple Calendar connecté avec succès.',
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Connection failed: ' . $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * POST /api/v1/calendar/sync/apple/sync
     */
    public function appleSync(Request $request): JsonResponse
    {
        SyncCalendarJob::dispatch($request->user()->id, 'apple');

        return response()->json(['message' => 'Synchronisation Apple Calendar lancée.', 'queued' => true]);
    }

    /**
     * DELETE /api/v1/calendar/sync/apple/disconnect
     */
    public function appleDisconnect(Request $request): JsonResponse
    {
        $this->appleService->disconnect($request->user()->id);

        return response()->json(['message' => 'Apple Calendar déconnecté.']);
    }

    // -----------------------------------------------------------------------
    // Sync status
    // -----------------------------------------------------------------------

    /**
     * GET /api/v1/calendar/sync/status
     * Return sync connection status for all providers.
     */
    public function status(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $tokens = CalendarSyncToken::where('user_id', $userId)
            ->get()
            ->keyBy('provider');

        $providers = ['google', 'outlook', 'apple'];
        $result    = [];

        foreach ($providers as $provider) {
            $token = $tokens->get($provider);
            $result[$provider] = [
                'connected'     => $token !== null,
                'last_synced_at' => $token?->last_synced_at?->toIso8601String(),
                'has_errors'    => ! empty($token?->sync_errors),
                'sync_errors'   => $token?->sync_errors ?? [],
            ];
        }

        return response()->json($result);
    }

    // -----------------------------------------------------------------------
    // Webhooks
    // -----------------------------------------------------------------------

    /**
     * POST /api/v1/calendar/webhooks/google
     * Receive Google push notification and re-queue a sync.
     */
    public function webhookGoogle(Request $request): Response
    {
        $userId = $this->resolveUserFromGoogleWebhook($request);

        if ($userId) {
            SyncCalendarJob::dispatch($userId, 'google');
        }

        return response('', Response::HTTP_OK);
    }

    /**
     * POST /api/v1/calendar/webhooks/outlook
     * Receive Outlook change notification.
     */
    public function webhookOutlook(Request $request): JsonResponse
    {
        // Outlook requires validation token in response during subscription creation
        if ($request->has('validationToken')) {
            return response()->json(
                $request->input('validationToken'),
                Response::HTTP_OK,
                ['Content-Type' => 'text/plain'],
            );
        }

        $notifications = $request->input('value', []);

        foreach ($notifications as $notification) {
            $clientState = $notification['clientState'] ?? '';

            if (str_starts_with($clientState, 'wh-calendar-')) {
                $userId = (int) str_replace('wh-calendar-', '', $clientState);

                if ($userId > 0) {
                    SyncCalendarJob::dispatch($userId, 'outlook');
                }
            }
        }

        return response()->json(null, Response::HTTP_ACCEPTED);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function resolveUserFromGoogleWebhook(Request $request): ?int
    {
        // Google sends X-Goog-Channel-ID header; we set it as "wh-cal-{userId}-..."
        $channelId = $request->header('X-Goog-Channel-ID', '');

        if (preg_match('/^wh-cal-(\d+)-/', $channelId, $m)) {
            return (int) $m[1];
        }

        return null;
    }
}
