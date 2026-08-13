<?php

declare(strict_types=1);

namespace Modules\Calendar\Services;

use Carbon\Carbon;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Calendar\Models\Calendar;
use Modules\Calendar\Models\CalendarEvent;
use Modules\Calendar\Models\CalendarSyncToken;

class GoogleCalendarService
{
    private const AUTH_ENDPOINT  = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';
    private const API_BASE       = 'https://www.googleapis.com/calendar/v3';
    private const SCOPE          = 'https://www.googleapis.com/auth/calendar';

    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $redirectUri,
    ) {}

    // -----------------------------------------------------------------------
    // OAuth2 flow
    // -----------------------------------------------------------------------

    /** Build the Google OAuth consent URL. */
    public function getAuthUrl(int $userId): string
    {
        $params = http_build_query([
            'client_id'     => $this->clientId,
            'redirect_uri'  => $this->redirectUri,
            'response_type' => 'code',
            'scope'         => self::SCOPE,
            'access_type'   => 'offline',
            'prompt'        => 'consent',
            'state'         => base64_encode((string) $userId),
        ]);

        return self::AUTH_ENDPOINT . '?' . $params;
    }

    /**
     * Exchange the authorization code for access/refresh tokens and persist them.
     *
     * @throws RequestException
     */
    public function handleCallback(int $userId, string $code): CalendarSyncToken
    {
        $response = Http::asForm()->post(self::TOKEN_ENDPOINT, [
            'code'          => $code,
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri'  => $this->redirectUri,
            'grant_type'    => 'authorization_code',
        ])->throw();

        $data = $response->json();

        return CalendarSyncToken::updateOrCreate(
            ['user_id' => $userId, 'provider' => 'google'],
            [
                'access_token'     => Crypt::encryptString($data['access_token']),
                'refresh_token'    => isset($data['refresh_token'])
                    ? Crypt::encryptString($data['refresh_token'])
                    : null,
                'token_expires_at' => now()->addSeconds((int) ($data['expires_in'] ?? 3600)),
                'sync_errors'      => null,
            ],
        );
    }

    /**
     * Refresh an expired access token using the stored refresh token.
     *
     * @throws \RuntimeException
     */
    public function refreshToken(CalendarSyncToken $syncToken): CalendarSyncToken
    {
        if (! $syncToken->refresh_token) {
            throw new \RuntimeException('No refresh token stored for Google Calendar.');
        }

        $response = Http::asForm()->post(self::TOKEN_ENDPOINT, [
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => Crypt::decryptString($syncToken->refresh_token),
            'grant_type'    => 'refresh_token',
        ])->throw();

        $data = $response->json();

        $syncToken->update([
            'access_token'     => Crypt::encryptString($data['access_token']),
            'token_expires_at' => now()->addSeconds((int) ($data['expires_in'] ?? 3600)),
        ]);

        return $syncToken->fresh();
    }

    // -----------------------------------------------------------------------
    // Sync: pull from Google
    // -----------------------------------------------------------------------

    /**
     * Pull all events from Google Calendar and persist locally.
     *
     * @throws \RuntimeException
     */
    public function syncFromGoogle(int $userId): int
    {
        $syncToken = $this->getValidToken($userId);
        if (! $syncToken) {
            return 0;
        }

        $accessToken = Crypt::decryptString($syncToken->access_token);

        // Fetch calendar list
        $calendarsResponse = Http::withToken($accessToken)
            ->get(self::API_BASE . '/users/me/calendarList')
            ->throw();

        $calendarItems = $calendarsResponse->json('items', []);
        $synced = 0;

        foreach ($calendarItems as $gCal) {
            // Find or create a local Calendar record
            $localCalendar = Calendar::firstOrCreate(
                ['user_id' => $userId, 'external_calendar_id' => $gCal['id'], 'source' => 'google'],
                [
                    'name'       => $gCal['summary'] ?? 'Google Calendar',
                    'color'      => $gCal['backgroundColor'] ?? '#3B82F6',
                    'type'       => 'shared',
                    'is_primary' => $gCal['primary'] ?? false,
                    'is_visible' => true,
                ],
            );

            // Fetch events (up to 12 months)
            $timeMin = now()->subMonth()->toRfc3339String();
            $timeMax = now()->addYear()->toRfc3339String();

            $eventsResponse = Http::withToken($accessToken)
                ->get(self::API_BASE . "/calendars/{$gCal['id']}/events", [
                    'timeMin'      => $timeMin,
                    'timeMax'      => $timeMax,
                    'singleEvents' => 'true',
                    'maxResults'   => 2500,
                ])
                ->throw();

            $events = $eventsResponse->json('items', []);

            foreach ($events as $gEvent) {
                if ($gEvent['status'] === 'cancelled') {
                    continue;
                }

                $this->upsertGoogleEvent($localCalendar, $gEvent, $userId);
                $synced++;
            }
        }

        $syncToken->update(['last_synced_at' => now(), 'sync_errors' => null]);

        return $synced;
    }

    // -----------------------------------------------------------------------
    // Push: write to Google
    // -----------------------------------------------------------------------

    /**
     * Create or update a local CalendarEvent in Google Calendar.
     *
     * @throws \RuntimeException
     */
    public function pushToGoogle(CalendarEvent $event): void
    {
        $calendar = $event->calendar;
        if (! $calendar || $calendar->source !== 'google') {
            return;
        }

        $syncToken = $this->getValidToken($calendar->user_id);
        if (! $syncToken) {
            return;
        }

        $accessToken = Crypt::decryptString($syncToken->access_token);
        $body = $this->eventToGoogleFormat($event);

        if ($event->external_event_id) {
            // Update existing
            Http::withToken($accessToken)
                ->put(self::API_BASE . "/calendars/{$calendar->external_calendar_id}/events/{$event->external_event_id}", $body)
                ->throw();
        } else {
            // Create new
            $response = Http::withToken($accessToken)
                ->post(self::API_BASE . "/calendars/{$calendar->external_calendar_id}/events", $body)
                ->throw();

            $event->update([
                'external_event_id' => $response->json('id'),
                'external_etag'     => $response->json('etag'),
            ]);
        }
    }

    /**
     * Delete a Google Calendar event by its external ID.
     *
     * @throws \RuntimeException
     */
    public function deleteFromGoogle(int $userId, string $calendarId, string $externalEventId): void
    {
        $syncToken = $this->getValidToken($userId);
        if (! $syncToken) {
            return;
        }

        $accessToken = Crypt::decryptString($syncToken->access_token);

        Http::withToken($accessToken)
            ->delete(self::API_BASE . "/calendars/{$calendarId}/events/{$externalEventId}")
            ->throw();
    }

    /**
     * Subscribe to push notifications via Google's channel/watch API.
     */
    public function watchCalendar(int $userId, string $calendarId): array
    {
        $syncToken = $this->getValidToken($userId);
        if (! $syncToken) {
            return [];
        }

        $accessToken = Crypt::decryptString($syncToken->access_token);
        $channelId   = 'wh-cal-' . $userId . '-' . uniqid('', true);

        $response = Http::withToken($accessToken)
            ->post(self::API_BASE . "/calendars/{$calendarId}/events/watch", [
                'id'      => $channelId,
                'type'    => 'web_hook',
                'address' => url('/api/v1/calendar/webhooks/google'),
                'expiration' => now()->addDays(7)->timestamp * 1000, // milliseconds
            ]);

        return $response->json() ?? [];
    }

    // -----------------------------------------------------------------------
    // Revoke
    // -----------------------------------------------------------------------

    public function disconnect(int $userId): void
    {
        $syncToken = CalendarSyncToken::where('user_id', $userId)->where('provider', 'google')->first();
        if (! $syncToken) {
            return;
        }

        try {
            Http::post('https://oauth2.googleapis.com/revoke', [
                'token' => Crypt::decryptString($syncToken->access_token),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to revoke Google token', ['error' => $e->getMessage()]);
        }

        $syncToken->delete();
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function getValidToken(int $userId): ?CalendarSyncToken
    {
        $token = CalendarSyncToken::where('user_id', $userId)->where('provider', 'google')->first();
        if (! $token) {
            return null;
        }

        if ($token->needsRefresh()) {
            try {
                $token = $this->refreshToken($token);
            } catch (\Throwable $e) {
                Log::error('Google token refresh failed', ['user_id' => $userId, 'error' => $e->getMessage()]);

                return null;
            }
        }

        return $token;
    }

    /** @param array<string,mixed> $gEvent */
    private function upsertGoogleEvent(Calendar $localCalendar, array $gEvent, int $userId): void
    {
        $startAt = $this->parseGoogleDateTime($gEvent['start'] ?? []);
        $endAt   = $this->parseGoogleDateTime($gEvent['end'] ?? []);
        $allDay  = isset($gEvent['start']['date']); // "date" key means all-day

        CalendarEvent::updateOrCreate(
            ['external_event_id' => $gEvent['id'], 'source' => 'google'],
            [
                'calendar_id'   => $localCalendar->id,
                'title'         => $gEvent['summary'] ?? '(Sans titre)',
                'description'   => $gEvent['description'] ?? null,
                'start_at'      => $startAt,
                'end_at'        => $endAt,
                'all_day'       => $allDay,
                'location'      => $gEvent['location'] ?? null,
                'status'        => $gEvent['status'] ?? 'confirmed',
                'visibility'    => $gEvent['visibility'] ?? 'public',
                'external_etag' => $gEvent['etag'] ?? null,
                'created_by'    => $userId,
            ],
        );
    }

    /** @param array<string,mixed> $dt */
    private function parseGoogleDateTime(array $dt): Carbon
    {
        if (isset($dt['dateTime'])) {
            return Carbon::parse($dt['dateTime']);
        }

        if (isset($dt['date'])) {
            return Carbon::parse($dt['date']);
        }

        return now();
    }

    /** @return array<string,mixed> */
    private function eventToGoogleFormat(CalendarEvent $event): array
    {
        $body = [
            'summary'     => $event->title,
            'description' => $event->description,
            'location'    => $event->location,
            'status'      => $event->status,
        ];

        if ($event->all_day) {
            $body['start'] = ['date' => $event->start_at->toDateString()];
            $body['end']   = ['date' => $event->end_at->toDateString()];
        } else {
            $body['start'] = ['dateTime' => $event->start_at->toRfc3339String(), 'timeZone' => config('app.timezone')];
            $body['end']   = ['dateTime' => $event->end_at->toRfc3339String(), 'timeZone' => config('app.timezone')];
        }

        if ($event->recurrence_rule) {
            $body['recurrence'] = ['RRULE:' . $event->recurrence_rule];
        }

        return $body;
    }
}
