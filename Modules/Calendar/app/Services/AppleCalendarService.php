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

/**
 * CalDAV-based Apple Calendar (iCloud) integration.
 * Implements RFC 4791 (CalDAV) and RFC 5545 (iCalendar).
 */
class AppleCalendarService
{
    public function __construct(
        private readonly string $calDavServer = 'https://caldav.icloud.com',
    ) {}

    // -----------------------------------------------------------------------
    // Connect via CalDAV
    // -----------------------------------------------------------------------

    /**
     * Store Apple Calendar (iCloud) credentials and verify the connection.
     *
     * @throws \RuntimeException
     */
    public function connect(int $userId, string $email, string $appPassword, ?string $serverUrl = null): CalendarSyncToken
    {
        $server = $serverUrl ?? $this->calDavServer;

        // Verify credentials with a PROPFIND request
        $response = Http::withBasicAuth($email, $appPassword)
            ->withHeaders(['Depth' => '0', 'Content-Type' => 'application/xml'])
            ->send('PROPFIND', $server . '/');

        if ($response->failed()) {
            throw new \RuntimeException('Could not connect to Apple Calendar. Check your credentials.');
        }

        return CalendarSyncToken::updateOrCreate(
            ['user_id' => $userId, 'provider' => 'apple'],
            [
                'access_token'  => Crypt::encryptString($appPassword),
                'refresh_token' => Crypt::encryptString($email), // store email in refresh_token field
                'calendar_ids'  => [['server' => $server]],
                'sync_errors'   => null,
            ],
        );
    }

    // -----------------------------------------------------------------------
    // Sync: pull from Apple via CalDAV
    // -----------------------------------------------------------------------

    public function syncFromApple(int $userId, ?string $serverUrl = null): int
    {
        $syncToken = CalendarSyncToken::where('user_id', $userId)->where('provider', 'apple')->first();
        if (! $syncToken) {
            return 0;
        }

        $email       = Crypt::decryptString($syncToken->refresh_token);
        $appPassword = Crypt::decryptString($syncToken->access_token);
        $server      = $serverUrl ?? ($syncToken->calendar_ids[0]['server'] ?? $this->calDavServer);

        // PROPFIND to discover calendars
        $propfindXml = <<<'XML'
            <?xml version="1.0" encoding="utf-8"?>
            <d:propfind xmlns:d="DAV:" xmlns:cal="urn:ietf:params:xml:ns:caldav">
              <d:prop>
                <d:displayname/>
                <cal:calendar-description/>
                <d:resourcetype/>
              </d:prop>
            </d:propfind>
            XML;

        $response = Http::withBasicAuth($email, $appPassword)
            ->withHeaders(['Depth' => '1', 'Content-Type' => 'application/xml'])
            ->withBody($propfindXml, 'application/xml')
            ->send('PROPFIND', $server . '/');

        if ($response->failed()) {
            $syncToken->update(['sync_errors' => ['error' => 'PROPFIND failed', 'status' => $response->status()]]);

            return 0;
        }

        // Parse calendar hrefs from XML response
        $hrefs = $this->parseCalendarHrefs($response->body());
        $synced = 0;

        foreach ($hrefs as $href) {
            $calendarName = basename($href);

            $localCalendar = Calendar::firstOrCreate(
                ['user_id' => $userId, 'external_calendar_id' => $href, 'source' => 'apple'],
                [
                    'name'       => $calendarName ?: 'Apple Calendar',
                    'color'      => '#6B7280',
                    'type'       => 'personal',
                    'is_visible' => true,
                ],
            );

            // REPORT to fetch events
            $reportXml = <<<'XML'
                <?xml version="1.0" encoding="utf-8"?>
                <cal:calendar-query xmlns:d="DAV:" xmlns:cal="urn:ietf:params:xml:ns:caldav">
                  <d:prop><d:getetag/><cal:calendar-data/></d:prop>
                  <cal:filter>
                    <cal:comp-filter name="VCALENDAR">
                      <cal:comp-filter name="VEVENT"/>
                    </cal:comp-filter>
                  </cal:filter>
                </cal:calendar-query>
                XML;

            $reportResponse = Http::withBasicAuth($email, $appPassword)
                ->withHeaders(['Depth' => '1', 'Content-Type' => 'application/xml'])
                ->withBody($reportXml, 'application/xml')
                ->send('REPORT', $server . $href);

            if ($reportResponse->failed()) {
                continue;
            }

            $vEvents = $this->parseVEventsFromXml($reportResponse->body());

            foreach ($vEvents as $vEvent) {
                $this->upsertVEvent($localCalendar, $vEvent, $userId);
                $synced++;
            }
        }

        $syncToken->update(['last_synced_at' => now(), 'sync_errors' => null]);

        return $synced;
    }

    // -----------------------------------------------------------------------
    // Push: write to Apple via CalDAV PUT
    // -----------------------------------------------------------------------

    public function pushToApple(CalendarEvent $event): void
    {
        $calendar = $event->calendar;
        if (! $calendar || $calendar->source !== 'apple') {
            return;
        }

        $syncToken = CalendarSyncToken::where('user_id', $calendar->user_id)
            ->where('provider', 'apple')
            ->first();

        if (! $syncToken) {
            return;
        }

        $email       = Crypt::decryptString($syncToken->refresh_token);
        $appPassword = Crypt::decryptString($syncToken->access_token);
        $server      = $syncToken->calendar_ids[0]['server'] ?? $this->calDavServer;

        $uid       = $event->external_event_id ?? (string) str()->uuid();
        $href      = $calendar->external_calendar_id . '/' . $uid . '.ics';
        $iCalData  = $this->eventToVEvent($event, $uid);

        Http::withBasicAuth($email, $appPassword)
            ->withHeaders(['Content-Type' => 'text/calendar; charset=utf-8'])
            ->withBody($iCalData, 'text/calendar')
            ->put($server . $href);

        if (! $event->external_event_id) {
            $event->update(['external_event_id' => $uid]);
        }
    }

    public function deleteFromApple(int $userId, string $href): void
    {
        $syncToken = CalendarSyncToken::where('user_id', $userId)->where('provider', 'apple')->first();
        if (! $syncToken) {
            return;
        }

        $email       = Crypt::decryptString($syncToken->refresh_token);
        $appPassword = Crypt::decryptString($syncToken->access_token);
        $server      = $syncToken->calendar_ids[0]['server'] ?? $this->calDavServer;

        Http::withBasicAuth($email, $appPassword)->delete($server . $href);
    }

    public function disconnect(int $userId): void
    {
        CalendarSyncToken::where('user_id', $userId)->where('provider', 'apple')->delete();
    }

    // -----------------------------------------------------------------------
    // iCal generation (RFC 5545 VEVENT)
    // -----------------------------------------------------------------------

    public function eventToVEvent(CalendarEvent $event, string $uid): string
    {
        $dtStamp = now()->format('Ymd\THis\Z');
        $dtStart = $event->all_day
            ? 'DTSTART;VALUE=DATE:' . $event->start_at->format('Ymd')
            : 'DTSTART:' . $event->start_at->utc()->format('Ymd\THis\Z');
        $dtEnd = $event->all_day
            ? 'DTEND;VALUE=DATE:' . $event->end_at->format('Ymd')
            : 'DTEND:' . $event->end_at->utc()->format('Ymd\THis\Z');

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//WideHalo ERP//Calendar//EN',
            'BEGIN:VEVENT',
            'UID:' . $uid,
            'DTSTAMP:' . $dtStamp,
            $dtStart,
            $dtEnd,
            'SUMMARY:' . $this->escapeIcal($event->title),
        ];

        if ($event->description) {
            $lines[] = 'DESCRIPTION:' . $this->escapeIcal($event->description);
        }

        if ($event->location) {
            $lines[] = 'LOCATION:' . $this->escapeIcal($event->location);
        }

        if ($event->recurrence_rule) {
            $lines[] = 'RRULE:' . $event->recurrence_rule;
        }

        $lines[] = 'STATUS:' . strtoupper($event->status);
        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines) . "\r\n";
    }

    // -----------------------------------------------------------------------
    // XML / iCal parsing helpers
    // -----------------------------------------------------------------------

    /** @return string[] */
    private function parseCalendarHrefs(string $xml): array
    {
        $hrefs = [];

        try {
            $doc = new \DOMDocument();
            @$doc->loadXML($xml);
            $nodes = $doc->getElementsByTagNameNS('DAV:', 'href');

            foreach ($nodes as $node) {
                $href = trim($node->textContent);
                if (str_ends_with($href, '/') && ! str_ends_with($href, '//')) {
                    $hrefs[] = $href;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('CalDAV PROPFIND parse error', ['error' => $e->getMessage()]);
        }

        return $hrefs;
    }

    /**
     * Extract VEVENT blocks from a CalDAV REPORT XML response.
     *
     * @return array<int, array<string,string>>
     */
    private function parseVEventsFromXml(string $xml): array
    {
        $events = [];

        try {
            $doc = new \DOMDocument();
            @$doc->loadXML($xml);
            $calDataNodes = $doc->getElementsByTagNameNS('urn:ietf:params:xml:ns:caldav', 'calendar-data');

            foreach ($calDataNodes as $node) {
                $ical     = $node->textContent;
                $parsed   = $this->parseVEventBlock($ical);

                if (! empty($parsed)) {
                    $events[] = $parsed;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('CalDAV REPORT parse error', ['error' => $e->getMessage()]);
        }

        return $events;
    }

    /**
     * Parse a VEVENT block from iCal text into a key-value array.
     *
     * @return array<string,string>
     */
    private function parseVEventBlock(string $ical): array
    {
        $inEvent = false;
        $data    = [];

        foreach (explode("\n", $ical) as $line) {
            $line = trim($line);

            if ($line === 'BEGIN:VEVENT') {
                $inEvent = true;

                continue;
            }

            if ($line === 'END:VEVENT') {
                break;
            }

            if ($inEvent && str_contains($line, ':')) {
                [$key, $value] = explode(':', $line, 2);
                $data[strtoupper(explode(';', $key)[0])] = $value;
            }
        }

        return $data;
    }

    /** @param array<string,string> $vEvent */
    private function upsertVEvent(Calendar $localCalendar, array $vEvent, int $userId): void
    {
        $uid     = $vEvent['UID'] ?? null;
        if (! $uid) {
            return;
        }

        $allDay  = isset($vEvent['DTSTART']) && ! str_contains($vEvent['DTSTART'], 'T');
        $startAt = Carbon::parse($vEvent['DTSTART'] ?? now());
        $endAt   = Carbon::parse($vEvent['DTEND'] ?? now()->addHour());

        CalendarEvent::updateOrCreate(
            ['external_event_id' => $uid, 'source' => 'apple'],
            [
                'calendar_id' => $localCalendar->id,
                'title'       => $vEvent['SUMMARY'] ?? '(Sans titre)',
                'description' => $vEvent['DESCRIPTION'] ?? null,
                'start_at'    => $startAt,
                'end_at'      => $endAt,
                'all_day'     => $allDay,
                'location'    => $vEvent['LOCATION'] ?? null,
                'status'      => strtolower($vEvent['STATUS'] ?? 'confirmed'),
                'created_by'  => $userId,
            ],
        );
    }

    private function escapeIcal(string $text): string
    {
        return str_replace(
            ['\\', ';', ',', "\n"],
            ['\\\\', '\;', '\,', '\n'],
            $text,
        );
    }
}
