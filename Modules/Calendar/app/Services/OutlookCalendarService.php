<?php

declare(strict_types=1);

namespace Modules\Calendar\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Calendar\Models\Calendar;
use Modules\Calendar\Models\CalendarEvent;
use Modules\Calendar\Models\CalendarSyncToken;

class OutlookCalendarService
{
    private const AUTH_BASE    = 'https://login.microsoftonline.com';
    private const GRAPH_BASE   = 'https://graph.microsoft.com/v1.0';
    private const SCOPE        = 'https://graph.microsoft.com/Calendars.ReadWrite offline_access';

    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $tenantId,
        private readonly string $redirectUri,
    ) {}

    // -----------------------------------------------------------------------
    // OAuth2
    // -----------------------------------------------------------------------

    public function getAuthUrl(int $userId): string
    {
        $params = http_build_query([
            'client_id'     => $this->clientId,
            'response_type' => 'code',
            'redirect_uri'  => $this->redirectUri,
            'scope'         => self::SCOPE,
            'response_mode' => 'query',
            'state'         => base64_encode((string) $userId),
        ]);

        return self::AUTH_BASE . "/{$this->tenantId}/oauth2/v2.0/authorize?{$params}";
    }

    public function handleCallback(int $userId, string $code): CalendarSyncToken
    {
        $response = Http::asForm()
            ->post(self::AUTH_BASE . "/{$this->tenantId}/oauth2/v2.0/token", [
                'client_id'     => $this->clientId,
                'client_secret' => $this->clientSecret,
                'code'          => $code,
                'redirect_uri'  => $this->redirectUri,
                'grant_type'    => 'authorization_code',
                'scope'         => self::SCOPE,
            ])
            ->throw();

        $data = $response->json();

        return CalendarSyncToken::updateOrCreate(
            ['user_id' => $userId, 'provider' => 'outlook'],
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

    public function refreshToken(CalendarSyncToken $syncToken): CalendarSyncToken
    {
        if (! $syncToken->refresh_token) {
            throw new \RuntimeException('No Outlook refresh token stored.');
        }

        $response = Http::asForm()
            ->post(self::AUTH_BASE . "/{$this->tenantId}/oauth2/v2.0/token", [
                'client_id'     => $this->clientId,
                'client_secret' => $this->clientSecret,
                'refresh_token' => Crypt::decryptString($syncToken->refresh_token),
                'grant_type'    => 'refresh_token',
                'scope'         => self::SCOPE,
            ])
            ->throw();

        $data = $response->json();

        $syncToken->update([
            'access_token'     => Crypt::encryptString($data['access_token']),
            'token_expires_at' => now()->addSeconds((int) ($data['expires_in'] ?? 3600)),
        ]);

        return $syncToken->fresh();
    }

    // -----------------------------------------------------------------------
    // Sync: pull from Outlook via Microsoft Graph
    // -----------------------------------------------------------------------

    public function syncFromOutlook(int $userId): int
    {
        $syncToken = $this->getValidToken($userId);
        if (! $syncToken) {
            return 0;
        }

        $accessToken = Crypt::decryptString($syncToken->access_token);

        // Get default calendar
        $calendarResponse = Http::withToken($accessToken)
            ->get(self::GRAPH_BASE . '/me/calendar')
            ->throw();

        $calData = $calendarResponse->json();

        $localCalendar = Calendar::firstOrCreate(
            ['user_id' => $userId, 'external_calendar_id' => $calData['id'], 'source' => 'outlook'],
            [
                'name'       => $calData['name'] ?? 'Outlook Calendar',
                'color'      => '#F97316', // orange
                'type'       => 'personal',
                'is_primary' => true,
                'is_visible' => true,
            ],
        );

        // Fetch events using delta — falls back to full pull
        $url   = self::GRAPH_BASE . '/me/events';
        $synced = 0;

        do {
            $response = Http::withToken($accessToken)
                ->get($url, [
                    '$select' => 'subject,bodyPreview,start,end,location,isAllDay,isCancelled,recurrence,id,lastModifiedDateTime',
                    '$top'    => 100,
                ])
                ->throw();

            $data  = $response->json();
            $items = $data['value'] ?? [];

            foreach ($items as $event) {
                if ($event['isCancelled'] ?? false) {
                    continue;
                }

                $this->upsertOutlookEvent($localCalendar, $event, $userId);
                $synced++;
            }

            $url = $data['@odata.nextLink'] ?? null;
        } while ($url);

        $syncToken->update(['last_synced_at' => now(), 'sync_errors' => null]);

        return $synced;
    }

    // -----------------------------------------------------------------------
    // Push: write to Outlook
    // -----------------------------------------------------------------------

    public function pushToOutlook(CalendarEvent $event): void
    {
        $calendar = $event->calendar;
        if (! $calendar || $calendar->source !== 'outlook') {
            return;
        }

        $syncToken = $this->getValidToken($calendar->user_id);
        if (! $syncToken) {
            return;
        }

        $accessToken = Crypt::decryptString($syncToken->access_token);
        $body        = $this->eventToGraphFormat($event);

        if ($event->external_event_id) {
            Http::withToken($accessToken)
                ->patch(self::GRAPH_BASE . "/me/events/{$event->external_event_id}", $body)
                ->throw();
        } else {
            $response = Http::withToken($accessToken)
                ->post(self::GRAPH_BASE . '/me/events', $body)
                ->throw();

            $event->update(['external_event_id' => $response->json('id')]);
        }
    }

    public function deleteFromOutlook(int $userId, string $externalEventId): void
    {
        $syncToken = $this->getValidToken($userId);
        if (! $syncToken) {
            return;
        }

        $accessToken = Crypt::decryptString($syncToken->access_token);

        Http::withToken($accessToken)
            ->delete(self::GRAPH_BASE . "/me/events/{$externalEventId}")
            ->throw();
    }

    /**
     * Subscribe to change notifications (delta/webhook) for efficient updates.
     */
    public function subscribeToDelta(int $userId): array
    {
        $syncToken = $this->getValidToken($userId);
        if (! $syncToken) {
            return [];
        }

        $accessToken = Crypt::decryptString($syncToken->access_token);

        $response = Http::withToken($accessToken)
            ->post(self::GRAPH_BASE . '/subscriptions', [
                'changeType'         => 'created,updated,deleted',
                'notificationUrl'    => url('/api/v1/calendar/webhooks/outlook'),
                'resource'           => 'me/events',
                'expirationDateTime' => now()->addDays(3)->toIso8601String(),
                'clientState'        => 'wh-calendar-' . $userId,
            ]);

        return $response->json() ?? [];
    }

    public function disconnect(int $userId): void
    {
        CalendarSyncToken::where('user_id', $userId)->where('provider', 'outlook')->delete();
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function getValidToken(int $userId): ?CalendarSyncToken
    {
        $token = CalendarSyncToken::where('user_id', $userId)->where('provider', 'outlook')->first();
        if (! $token) {
            return null;
        }

        if ($token->needsRefresh()) {
            try {
                $token = $this->refreshToken($token);
            } catch (\Throwable $e) {
                Log::error('Outlook token refresh failed', ['user_id' => $userId, 'error' => $e->getMessage()]);

                return null;
            }
        }

        return $token;
    }

    /** @param array<string,mixed> $outlookEvent */
    private function upsertOutlookEvent(Calendar $localCalendar, array $outlookEvent, int $userId): void
    {
        $allDay  = $outlookEvent['isAllDay'] ?? false;
        $startAt = Carbon::parse($outlookEvent['start']['dateTime'] ?? now());
        $endAt   = Carbon::parse($outlookEvent['end']['dateTime'] ?? now()->addHour());

        CalendarEvent::updateOrCreate(
            ['external_event_id' => $outlookEvent['id'], 'source' => 'outlook'],
            [
                'calendar_id' => $localCalendar->id,
                'title'       => $outlookEvent['subject'] ?? '(Sans titre)',
                'description' => $outlookEvent['bodyPreview'] ?? null,
                'start_at'    => $startAt,
                'end_at'      => $endAt,
                'all_day'     => $allDay,
                'location'    => $outlookEvent['location']['displayName'] ?? null,
                'status'      => 'confirmed',
                'visibility'  => 'public',
                'created_by'  => $userId,
            ],
        );
    }

    /** @return array<string,mixed> */
    private function eventToGraphFormat(CalendarEvent $event): array
    {
        return [
            'subject' => $event->title,
            'body'    => ['contentType' => 'text', 'content' => $event->description ?? ''],
            'start'   => [
                'dateTime' => $event->start_at->toIso8601String(),
                'timeZone' => config('app.timezone'),
            ],
            'end' => [
                'dateTime' => $event->end_at->toIso8601String(),
                'timeZone' => config('app.timezone'),
            ],
            'location'  => ['displayName' => $event->location ?? ''],
            'isAllDay'  => $event->all_day,
        ];
    }
}
