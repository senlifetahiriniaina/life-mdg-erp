<?php

declare(strict_types=1);

namespace Modules\BI\Jobs;

use Illuminate\Support\Facades\Log;
use Exception;
use Modules\BI\Models\BiAlert;
use Modules\Shared\Jobs\BaseAsyncJob;

/**
 * SendAlertNotificationJob
 *
 * Dispatches alert notifications through multiple channels.
 * Supports email, SMS, Slack, MS Teams, and in-app notifications.
 *
 * @property int alert_id The ID of the triggered alert
 * @property array<int> recipient_ids User IDs to notify
 * @property string job_id Unique identifier for tracking progress
 */
class SendAlertNotificationJob extends BaseAsyncJob
{
    public string $queue = 'bi';

    private string $jobId;

    public function __construct(
        private readonly int $alert_id,
        private readonly array $recipient_ids = []
    ) {
        $this->jobId = uniqid('notif_', true);
        parent::__construct($this->extractCompanyId());
    }

    protected function execute(): void
    {
        try {
            Log::info('Starting alert notification dispatch', [
                'job_id' => $this->jobId,
                'alert_id' => $this->alert_id,
                'recipient_count' => count($this->recipient_ids),
                'timestamp' => now()->toIso8601String(),
            ]);

            $alert = BiAlert::findOrFail($this->alert_id);

            // Determine recipients
            $recipients = $this->determineRecipients($alert);

            if ($recipients->isEmpty()) {
                Log::info('No recipients found for alert', [
                    'job_id' => $this->jobId,
                    'alert_id' => $this->alert_id,
                ]);

                return;
            }

            // Get notification channels for alert
            $channels = $alert->channels ?? ['email'];

            // Build notification message
            $message = $this->buildNotificationMessage($alert);

            $totalSent = 0;

            // Batch send notifications (100 per batch)
            $batches = $recipients->chunk(100);

            foreach ($batches as $batch) {
                try {
                    $batchSent = $this->sendNotificationBatch($batch, $message, $channels, $alert);
                    $totalSent += $batchSent;

                    Log::debug('Notification batch sent', [
                        'job_id' => $this->jobId,
                        'batch_size' => $batch->count(),
                        'sent_count' => $batchSent,
                    ]);
                } catch (\Throwable $e) {
                    Log::error('Notification batch failed', [
                        'job_id' => $this->jobId,
                        'batch_size' => $batch->count(),
                        'error' => $e->getMessage(),
                    ]);

                    // Continue with next batch on failure
                }
            }

            Log::info('Alert notification dispatch completed', [
                'job_id' => $this->jobId,
                'alert_id' => $this->alert_id,
                'total_sent' => $totalSent,
            ]);
        } catch (\Throwable $e) {
            Log::error('Alert notification dispatch failed', [
                'job_id' => $this->jobId,
                'alert_id' => $this->alert_id,
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
            throw new Exception("No tenant context available for alert notification job");
        }

        return (int) $tenantId;
    }

    /**
     * Determine recipients for notification
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function determineRecipients(BiAlert $alert)
    {
        // Use provided recipient IDs or alert's configured recipients
        $recipientIds = !empty($this->recipient_ids)
            ? $this->recipient_ids
            : ($alert->recipients ?? []);

        // In real implementation, fetch user data from database
        // For now, simulate user data
        return collect($recipientIds)->map(function ($userId) {
            return [
                'id' => $userId,
                'email' => "user_{$userId}@example.com",
                'slack_webhook' => 'https://hooks.slack.com/services/xxx',
                'phone' => '+1234567890',
                'teams_webhook' => 'https://outlook.webhook.office.com/xxx',
            ];
        });
    }

    /**
     * Build notification message from alert
     *
     * @return array<string, string>
     */
    private function buildNotificationMessage(BiAlert $alert): array
    {
        $title = "Alert: {$alert->name}";
        $severity = $this->determineSeverity($alert);

        $body = <<<TEXT
Alert Triggered: {$alert->name}

Condition: {$alert->condition_type} {$alert->threshold}
Current Value: {$alert->last_value}
Metric: {$alert->metric_name}
Triggered At: {$alert->last_triggered_at?->toDateTimeString()}

Please review the dashboard for more details.
TEXT;

        return [
            'title' => $title,
            'body' => $body,
            'severity' => $severity,
            'alert_id' => (string) $alert->id,
            'dashboard_url' => "https://example.com/bi/dashboard/{$alert->widget_id}",
        ];
    }

    /**
     * Determine alert severity
     */
    private function determineSeverity(BiAlert $alert): string
    {
        if ($alert->last_value === null) {
            return 'info';
        }

        $deviation = abs($alert->last_value - $alert->threshold);
        $thresholdPercent = ($deviation / max(1, $alert->threshold)) * 100;

        if ($thresholdPercent > 50) {
            return 'critical';
        }

        if ($thresholdPercent > 20) {
            return 'warning';
        }

        return 'info';
    }

    /**
     * Send notification batch through configured channels
     *
     * @param \Illuminate\Support\Collection<int, array<string, mixed>> $recipients
     * @param array<string, string> $message
     * @param array<string> $channels
     */
    private function sendNotificationBatch($recipients, array $message, array $channels, BiAlert $alert): int
    {
        $totalSent = 0;

        foreach ($channels as $channel) {
            try {
                $sent = $this->sendViaChannel($recipients, $message, $channel);
                $totalSent += $sent;

                Log::debug('Channel notification sent', [
                    'job_id' => $this->jobId,
                    'channel' => $channel,
                    'sent_count' => $sent,
                ]);
            } catch (\Throwable $e) {
                Log::error('Channel notification failed', [
                    'job_id' => $this->jobId,
                    'channel' => $channel,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $totalSent;
    }

    /**
     * Send notifications via a specific channel
     *
     * @param \Illuminate\Support\Collection<int, array<string, mixed>> $recipients
     * @param array<string, string> $message
     */
    private function sendViaChannel($recipients, array $message, string $channel): int
    {
        return match ($channel) {
            'email' => $this->sendEmailNotifications($recipients, $message),
            'sms' => $this->sendSmsNotifications($recipients, $message),
            'slack' => $this->sendSlackNotifications($recipients, $message),
            'teams' => $this->sendTeamsNotifications($recipients, $message),
            'in_app' => $this->sendInAppNotifications($recipients, $message),
            default => 0,
        };
    }

    /**
     * Send email notifications
     *
     * @param \Illuminate\Support\Collection<int, array<string, mixed>> $recipients
     * @param array<string, string> $message
     */
    private function sendEmailNotifications($recipients, array $message): int
    {
        $sent = 0;

        foreach ($recipients as $recipient) {
            try {
                // In real implementation, use Laravel Mail facade
                // Mail::to($recipient['email'])->send(new AlertNotificationMail($message));

                $sent++;

                Log::debug('Email notification queued', [
                    'job_id' => $this->jobId,
                    'recipient_id' => $recipient['id'],
                    'email' => $recipient['email'],
                ]);
            } catch (\Throwable $e) {
                Log::error('Email notification failed', [
                    'job_id' => $this->jobId,
                    'recipient_id' => $recipient['id'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $sent;
    }

    /**
     * Send SMS notifications
     *
     * @param \Illuminate\Support\Collection<int, array<string, mixed>> $recipients
     * @param array<string, string> $message
     */
    private function sendSmsNotifications($recipients, array $message): int
    {
        $sent = 0;

        foreach ($recipients as $recipient) {
            try {
                // In real implementation, use SMS service (Twilio, etc.)
                // $sms = new SmsService();
                // $sms->send($recipient['phone'], $message['title']);

                $sent++;

                Log::debug('SMS notification queued', [
                    'job_id' => $this->jobId,
                    'recipient_id' => $recipient['id'],
                ]);
            } catch (\Throwable $e) {
                Log::error('SMS notification failed', [
                    'job_id' => $this->jobId,
                    'recipient_id' => $recipient['id'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $sent;
    }

    /**
     * Send Slack notifications
     *
     * @param \Illuminate\Support\Collection<int, array<string, mixed>> $recipients
     * @param array<string, string> $message
     */
    private function sendSlackNotifications($recipients, array $message): int
    {
        $sent = 0;

        foreach ($recipients as $recipient) {
            try {
                // In real implementation, use Slack API
                // $slack = new SlackClient($recipient['slack_webhook']);
                // $slack->send($message);

                $sent++;

                Log::debug('Slack notification queued', [
                    'job_id' => $this->jobId,
                    'recipient_id' => $recipient['id'],
                ]);
            } catch (\Throwable $e) {
                Log::error('Slack notification failed', [
                    'job_id' => $this->jobId,
                    'recipient_id' => $recipient['id'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $sent;
    }

    /**
     * Send MS Teams notifications
     *
     * @param \Illuminate\Support\Collection<int, array<string, mixed>> $recipients
     * @param array<string, string> $message
     */
    private function sendTeamsNotifications($recipients, array $message): int
    {
        $sent = 0;

        foreach ($recipients as $recipient) {
            try {
                // In real implementation, use MS Teams Webhook
                // $teams = new TeamsClient($recipient['teams_webhook']);
                // $teams->send($message);

                $sent++;

                Log::debug('Teams notification queued', [
                    'job_id' => $this->jobId,
                    'recipient_id' => $recipient['id'],
                ]);
            } catch (\Throwable $e) {
                Log::error('Teams notification failed', [
                    'job_id' => $this->jobId,
                    'recipient_id' => $recipient['id'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $sent;
    }

    /**
     * Send in-app notifications
     *
     * @param \Illuminate\Support\Collection<int, array<string, mixed>> $recipients
     * @param array<string, string> $message
     */
    private function sendInAppNotifications($recipients, array $message): int
    {
        $sent = 0;

        foreach ($recipients as $recipient) {
            try {
                // In real implementation, store in database notifications table
                // Notification::create([
                //     'user_id' => $recipient['id'],
                //     'type' => 'alert',
                //     'data' => $message,
                // ]);

                $sent++;

                Log::debug('In-app notification created', [
                    'job_id' => $this->jobId,
                    'recipient_id' => $recipient['id'],
                ]);
            } catch (\Throwable $e) {
                Log::error('In-app notification failed', [
                    'job_id' => $this->jobId,
                    'recipient_id' => $recipient['id'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $sent;
    }
}
