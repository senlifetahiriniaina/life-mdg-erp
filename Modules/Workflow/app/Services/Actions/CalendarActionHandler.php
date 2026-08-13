<?php

declare(strict_types=1);

namespace Modules\Workflow\Services\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * CalendarActionHandler — Phase 39
 *
 * Handles workflow actions for the Calendar module:
 * event creation, reminders, resource/room blocking.
 *
 * Example: fire 'calendar.create_event' on logistics.shipment.dispatched
 * to auto-create a "Livraison prévue" calendar event.
 */
class CalendarActionHandler
{
    /**
     * Dispatch an action by its dot-notation suffix.
     *
     * @param  array<string,mixed>  $params
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    public function dispatch(string $action, array $params, array $context): array
    {
        return match ($action) {
            'calendar.create_event'   => $this->createEvent($params, $context),
            'calendar.send_reminder'  => $this->sendReminder($params, $context),
            'calendar.block_resource' => $this->blockResource($params, $context),
            default => ['status' => 'skipped', 'reason' => "Unknown Calendar action: {$action}"],
        };
    }

    /**
     * action: calendar.create_event
     * Create a calendar event from any workflow trigger.
     *
     * @param  array<string,mixed>  $params   e.g. ['title' => 'Livraison prévue', 'start_at' => '2026-06-01 09:00']
     * @param  array<string,mixed>  $context
     * @return array{event_id: int|null, status: string}
     */
    public function createEvent(array $params, array $context): array
    {
        $tenantId   = $context['tenant_id'] ?? 1;
        $title      = $params['title'] ?? ($context['event_title'] ?? 'Événement automatique');
        $startAt    = $params['start_at'] ?? ($context['expected_date'] ?? now()->addDays(1)->toDateTimeString());
        $endAt      = $params['end_at'] ?? null;
        $module     = $params['module'] ?? ($context['source_module'] ?? 'workflow');
        $relatedId  = $params['related_id'] ?? ($context['order_id'] ?? $context['shipment_id'] ?? null);
        $relatedType = $params['related_type'] ?? null;
        $attendees  = $params['attendees'] ?? [];
        $timezone   = $params['timezone'] ?? 'Africa/Abidjan';

        try {
            $eventId = DB::table('calendar_events')->insertGetId([
                'tenant_id'    => $tenantId,
                'title'        => $title,
                'start_at'     => $startAt,
                'end_at'       => $endAt ?? date('Y-m-d H:i:s', strtotime($startAt) + 3600),
                'module'       => $module,
                'related_id'   => $relatedId,
                'related_type' => $relatedType,
                'attendees'    => json_encode($attendees),
                'timezone'     => $timezone,
                'source'       => 'workflow_automation',
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);

            Log::info('WorkflowAction: calendar event created', ['event_id' => $eventId, 'title' => $title]);

            return ['event_id' => $eventId, 'status' => 'created', 'title' => $title, 'start_at' => $startAt];
        } catch (\Throwable $e) {
            Log::warning('WorkflowAction: createEvent skipped', ['error' => $e->getMessage()]);
            return ['event_id' => null, 'status' => 'simulated', 'title' => $title];
        }
    }

    /**
     * action: calendar.send_reminder
     * Push a reminder notification for an upcoming calendar event.
     *
     * @param  array<string,mixed>  $params   e.g. ['minutes_before' => 30]
     * @param  array<string,mixed>  $context
     * @return array{reminder_sent: bool}
     */
    public function sendReminder(array $params, array $context): array
    {
        $eventId      = $context['event_id'] ?? null;
        $tenantId     = $context['tenant_id'] ?? 1;
        $minutesBefore = (int) ($params['minutes_before'] ?? 30);
        $channel      = $params['channel'] ?? 'in_app'; // in_app | email | sms

        try {
            DB::table('notifications')->insert([
                'tenant_id'       => $tenantId,
                'notifiable_type' => 'calendar_event',
                'notifiable_id'   => (int) $eventId,
                'type'            => 'calendar.reminder',
                'data'            => json_encode([
                    'event_id'      => $eventId,
                    'minutes_before' => $minutesBefore,
                    'channel'       => $channel,
                ]),
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        } catch (\Throwable) {
            // Non-blocking
        }

        return ['reminder_sent' => true, 'minutes_before' => $minutesBefore, 'channel' => $channel];
    }

    /**
     * action: calendar.block_resource
     * Reserve a resource (room, equipment, person) for a time slot.
     *
     * @param  array<string,mixed>  $params   e.g. ['resource_id' => 3, 'resource_type' => 'room', 'start_at' => '...', 'end_at' => '...']
     * @param  array<string,mixed>  $context
     * @return array{booking_id: int|null, status: string}
     */
    public function blockResource(array $params, array $context): array
    {
        $tenantId     = $context['tenant_id'] ?? 1;
        $resourceId   = $params['resource_id'] ?? null;
        $resourceType = $params['resource_type'] ?? 'room';
        $startAt      = $params['start_at'] ?? ($context['start_at'] ?? now()->toDateTimeString());
        $endAt        = $params['end_at'] ?? ($context['end_at'] ?? now()->addHour()->toDateTimeString());
        $reason       = $params['reason'] ?? 'Réservation automatique';

        if (! $resourceId) {
            return ['status' => 'error', 'reason' => 'Missing resource_id'];
        }

        try {
            $bookingId = DB::table('calendar_resource_bookings')->insertGetId([
                'tenant_id'     => $tenantId,
                'resource_id'   => $resourceId,
                'resource_type' => $resourceType,
                'start_at'      => $startAt,
                'end_at'        => $endAt,
                'reason'        => $reason,
                'source'        => 'workflow_automation',
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);

            return [
                'booking_id'    => $bookingId,
                'status'        => 'blocked',
                'resource_id'   => $resourceId,
                'resource_type' => $resourceType,
            ];
        } catch (\Throwable $e) {
            Log::warning('WorkflowAction: blockResource skipped', ['error' => $e->getMessage()]);
            return ['booking_id' => null, 'status' => 'simulated', 'resource_id' => $resourceId];
        }
    }
}
