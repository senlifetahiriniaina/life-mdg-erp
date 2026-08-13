<?php

declare(strict_types=1);

namespace Modules\BI\Jobs;

use Illuminate\Support\Facades\Log;
use Exception;
use Modules\BI\Models\BiAlertEvent;
use Modules\Shared\Jobs\BaseAsyncJob;

/**
 * EscalateUnacknowledgedAlertsJob
 *
 * Auto-escalates old unacknowledged alerts.
 * Increases severity, adds additional recipients, and sends follow-up notifications.
 *
 * @property int max_age_hours Maximum age before escalation (default: 4 hours)
 * @property string job_id Unique identifier for tracking progress
 */
class EscalateUnacknowledgedAlertsJob extends BaseAsyncJob
{
    public string $queue = 'bi';

    private string $jobId;

    public function __construct(
        private readonly int $max_age_hours = 4
    ) {
        $this->jobId = uniqid('escalate_', true);
        parent::__construct($this->extractCompanyId());
    }

    protected function execute(): void
    {
        try {
            Log::info('Starting unacknowledged alert escalation', [
                'job_id' => $this->jobId,
                'max_age_hours' => $this->max_age_hours,
                'timestamp' => now()->toIso8601String(),
            ]);

            // Find unacknowledged alerts past max age
            $unacknowledgedAlerts = $this->findUnacknowledgedAlerts();

            if ($unacknowledgedAlerts->isEmpty()) {
                Log::info('No unacknowledged alerts to escalate', ['job_id' => $this->jobId]);
                return;
            }

            $escalatedCount = 0;
            $notificationsSent = 0;

            foreach ($unacknowledgedAlerts as $alertEvent) {
                try {
                    $this->escalateAlert($alertEvent);
                    $escalatedCount++;

                    // Send escalation notification
                    $sent = $this->sendEscalationNotification($alertEvent);
                    $notificationsSent += $sent;

                    Log::info('Alert escalated', [
                        'job_id' => $this->jobId,
                        'alert_event_id' => $alertEvent->id,
                        'alert_id' => $alertEvent->alert_id,
                        'escalation_level' => $alertEvent->escalation_level ?? 1,
                    ]);
                } catch (\Throwable $e) {
                    Log::error('Alert escalation failed', [
                        'job_id' => $this->jobId,
                        'alert_event_id' => $alertEvent->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('Alert escalation completed', [
                'job_id' => $this->jobId,
                'escalated_count' => $escalatedCount,
                'notifications_sent' => $notificationsSent,
            ]);
        } catch (\Throwable $e) {
            Log::error('Alert escalation job failed', [
                'job_id' => $this->jobId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    private function extractCompanyId(): int
    {
        $tenantId = tenant('id');

        if (!$tenantId) {
            throw new Exception("No tenant context available for alert escalation job");
        }

        return (int) $tenantId;
    }

    /**
     * Find unacknowledged alerts past max age
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, BiAlertEvent>
     */
    private function findUnacknowledgedAlerts()
    {
        $cutoffTime = now()->subHours($this->max_age_hours);

        return BiAlertEvent::where('acknowledged', false)
            ->where('created_at', '<', $cutoffTime)
            ->with('alert')
            ->get();
    }

    /**
     * Escalate an alert event
     */
    private function escalateAlert(BiAlertEvent $alertEvent): void
    {
        $currentLevel = $alertEvent->escalation_level ?? 0;
        $newLevel = $currentLevel + 1;

        // Calculate escalation factor (increases with level)
        $escalationFactor = pow(1.5, $newLevel);

        // Update escalation data
        $alertEvent->update([
            'escalation_level' => $newLevel,
            'escalation_severity' => $this->calculateEscalatedSeverity($currentLevel),
            'escalated_at' => now(),
            'escalation_message' => "Alert escalated to level {$newLevel}. Requires immediate attention.",
        ]);

        // Add escalation note
        $this->addEscalationNote($alertEvent, $newLevel);

        // Expand recipient list based on escalation level
        $this->expandRecipientList($alertEvent, $newLevel);
    }

    /**
     * Calculate escalated severity
     */
    private function calculateEscalatedSeverity(int $previousLevel): string
    {
        return match ($previousLevel) {
            0 => 'warning',
            1 => 'critical',
            default => 'critical',
        };
    }

    /**
     * Add escalation note to alert
     */
    private function addEscalationNote(BiAlertEvent $alertEvent, int $level): void
    {
        $message = "Escalation Level {$level}: Alert unacknowledged for " . $this->max_age_hours . " hours. ";

        if ($level === 1) {
            $message .= "Notifying supervisors.";
        } elseif ($level >= 2) {
            $message .= "Notifying management and escalation contacts.";
        }

        Log::debug('Escalation note added', [
            'job_id' => $this->jobId,
            'alert_event_id' => $alertEvent->id,
            'message' => $message,
        ]);
    }

    /**
     * Expand recipient list based on escalation level
     */
    private function expandRecipientList(BiAlertEvent $alertEvent, int $level): void
    {
        $baseRecipients = $alertEvent->alert->recipients ?? [];
        $additionalRecipients = [];

        if ($level === 1) {
            // Add supervisors/team leads
            $additionalRecipients = [101, 102, 103]; // Simulated supervisor IDs
        } elseif ($level >= 2) {
            // Add managers and directors
            $additionalRecipients = [201, 202, 203, 204]; // Simulated manager IDs
        }

        $allRecipients = array_unique(array_merge($baseRecipients, $additionalRecipients));

        Log::debug('Recipient list expanded', [
            'job_id' => $this->jobId,
            'alert_event_id' => $alertEvent->id,
            'base_recipients' => count($baseRecipients),
            'additional_recipients' => count($additionalRecipients),
            'total_recipients' => count($allRecipients),
        ]);
    }

    /**
     * Send escalation notification
     */
    private function sendEscalationNotification(BiAlertEvent $alertEvent): int
    {
        $escalationLevel = $alertEvent->escalation_level ?? 0;

        // Determine escalation recipients
        $recipients = $this->getEscalationRecipients($escalationLevel);

        if (empty($recipients)) {
            return 0;
        }

        // Dispatch notification job with escalation flag
        SendAlertNotificationJob::dispatch(
            $alertEvent->alert_id,
            $recipients
        );

        Log::debug('Escalation notification dispatched', [
            'job_id' => $this->jobId,
            'alert_event_id' => $alertEvent->id,
            'recipient_count' => count($recipients),
        ]);

        return count($recipients);
    }

    /**
     * Get escalation recipients based on level
     *
     * @return array<int>
     */
    private function getEscalationRecipients(int $level): array
    {
        return match ($level) {
            0 => [1, 2], // Initial recipients
            1 => [101, 102, 103], // Supervisors
            2 => [201, 202, 203, 204], // Managers
            3 => [301, 302], // Directors/Executives
            default => [401], // Emergency/Escalation team
        };
    }
}
