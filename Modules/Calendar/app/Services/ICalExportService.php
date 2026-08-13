<?php

declare(strict_types=1);

namespace Modules\Calendar\Services;

use Illuminate\Support\Collection;
use Modules\Calendar\Models\CalendarEvent;

/**
 * Export CalendarEvents as a standards-compliant .ics file (RFC 5545).
 */
class ICalExportService
{
    /**
     * Generate a .ics string from a collection of CalendarEvents.
     *
     * @param Collection<int, CalendarEvent> $events
     */
    public function exportToIcs(Collection $events, string $calendarName = 'WideHalo Calendar'): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//WideHalo ERP//Calendar//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:' . $this->escape($calendarName),
            'X-WR-TIMEZONE:' . config('app.timezone', 'UTC'),
        ];

        foreach ($events as $event) {
            $lines = array_merge($lines, $this->buildVEvent($event));
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines) . "\r\n";
    }

    /** @return string[] */
    private function buildVEvent(CalendarEvent $event): array
    {
        $uid     = $event->external_event_id ?? 'wh-' . $event->id . '@widehalo.com';
        $dtStamp = now()->utc()->format('Ymd\THis\Z');

        $lines = [
            'BEGIN:VEVENT',
            'UID:' . $uid,
            'DTSTAMP:' . $dtStamp,
            'SUMMARY:' . $this->escape($event->title),
            'STATUS:' . strtoupper($event->status),
            'CLASS:' . ($event->visibility === 'private' ? 'PRIVATE' : 'PUBLIC'),
            'CREATED:' . ($event->created_at?->utc()->format('Ymd\THis\Z') ?? $dtStamp),
            'LAST-MODIFIED:' . ($event->updated_at?->utc()->format('Ymd\THis\Z') ?? $dtStamp),
        ];

        // Start/End
        if ($event->all_day) {
            $lines[] = 'DTSTART;VALUE=DATE:' . $event->start_at->format('Ymd');
            $lines[] = 'DTEND;VALUE=DATE:' . $event->end_at->addDay()->format('Ymd'); // RFC 5545: exclusive end
        } else {
            $lines[] = 'DTSTART:' . $event->start_at->utc()->format('Ymd\THis\Z');
            $lines[] = 'DTEND:' . $event->end_at->utc()->format('Ymd\THis\Z');
        }

        if ($event->description) {
            $lines[] = $this->foldLine('DESCRIPTION:' . $this->escape($event->description));
        }

        if ($event->location) {
            $lines[] = 'LOCATION:' . $this->escape($event->location);
        }

        if ($event->url) {
            $lines[] = 'URL:' . $event->url;
        }

        if ($event->recurrence_rule) {
            $lines[] = 'RRULE:' . $event->recurrence_rule;
        }

        if (! empty($event->recurrence_exception_dates)) {
            $exDates = collect($event->recurrence_exception_dates)
                ->map(fn ($d) => now()->parse($d)->utc()->format('Ymd\THis\Z'))
                ->implode(',');
            $lines[] = 'EXDATE:' . $exDates;
        }

        // Attendees
        if ($event->relationLoaded('attendees')) {
            foreach ($event->attendees as $attendee) {
                $cn     = $attendee->name ? 'CN="' . $attendee->name . '"' : '';
                $partstat = strtoupper(str_replace('-', '', $attendee->status));
                $role   = $attendee->is_organizer ? 'ROLE=CHAIR' : 'ROLE=REQ-PARTICIPANT';
                $lines[] = "ATTENDEE;{$cn};{$role};PARTSTAT={$partstat}:mailto:{$attendee->email}";
            }
        }

        // Reminders (VALARM)
        if ($event->relationLoaded('reminders')) {
            foreach ($event->reminders as $reminder) {
                $lines[] = 'BEGIN:VALARM';
                $lines[] = 'TRIGGER:-PT' . $reminder->minutes_before . 'M';
                $lines[] = 'ACTION:' . strtoupper($reminder->method === 'email' ? 'EMAIL' : 'DISPLAY');
                $lines[] = 'DESCRIPTION:Reminder: ' . $this->escape($event->title);
                $lines[] = 'END:VALARM';
            }
        }

        $lines[] = 'END:VEVENT';

        return $lines;
    }

    /** Escape special characters in iCal text values. */
    private function escape(string $text): string
    {
        return str_replace(
            ['\\', ';', ',', "\r\n", "\n", "\r"],
            ['\\\\', '\;', '\,', '\n', '\n', '\n'],
            $text,
        );
    }

    /**
     * Fold long lines at 75 octets per RFC 5545.
     * Simple single-fold — sufficient for descriptions.
     */
    private function foldLine(string $line): string
    {
        if (mb_strlen($line) <= 75) {
            return $line;
        }

        $result = '';
        $chunks = mb_str_split($line, 74);

        foreach ($chunks as $i => $chunk) {
            $result .= ($i === 0 ? '' : "\r\n ") . $chunk;
        }

        return $result;
    }
}
