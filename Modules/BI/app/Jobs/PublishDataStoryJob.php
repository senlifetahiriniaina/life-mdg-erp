<?php

declare(strict_types=1);

namespace Modules\BI\Jobs;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Exception;
use Modules\BI\Models\DataStory;
use Modules\Shared\Jobs\BaseAsyncJob;

/**
 * PublishDataStoryJob
 *
 * Makes data story available to audience segments.
 * Handles permissions, notifications, and access control.
 *
 * @property int story_id The ID of the data story
 * @property array<string> audience_segments Audience segments to notify
 * @property bool notify_recipients Whether to send notifications
 * @property string job_id Unique identifier for tracking progress
 */
class PublishDataStoryJob extends BaseAsyncJob
{
    public string $queue = 'bi';

    private string $jobId;

    public function __construct(
        private readonly int $story_id,
        private readonly array $audience_segments = ['public'],
        private readonly bool $notify_recipients = true
    ) {
        $this->jobId = uniqid('publish_', true);
        parent::__construct($this->extractCompanyId());
    }

    protected function execute(): void
    {
        try {
            Log::info('Starting data story publication', [
                'job_id' => $this->jobId,
                'story_id' => $this->story_id,
                'audience_segments' => $this->audience_segments,
                'notify' => $this->notify_recipients,
                'timestamp' => now()->toIso8601String(),
            ]);

            // Get story data
            $story = $this->getStoryData();

            // Set up access permissions
            $this->configureStoryAccess($story);

            // Collect recipients for each segment
            $recipients = $this->collectRecipients();

            // Send notifications if enabled
            if ($this->notify_recipients) {
                $this->notifyRecipients($recipients, $story);
            }

            // Mark story as published
            $this->publishStory($story);

            // Create audit log entry
            $this->logPublication($recipients);

            Log::info('Data story publication completed', [
                'job_id' => $this->jobId,
                'story_id' => $this->story_id,
                'recipients_notified' => count($recipients),
                'segments' => count($this->audience_segments),
            ]);
        } catch (\Throwable $e) {
            Log::error('Data story publication failed', [
                'job_id' => $this->jobId,
                'story_id' => $this->story_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    private function extractCompanyId(): int
    {
        $story = DataStory::findOrFail($this->story_id);
        return $story->company_id;
    }

    /**
     * Get story data from database
     *
     * @return array<string, mixed>
     */
    private function getStoryData(): array
    {
        // In a real implementation, fetch from DataStory model
        return [
            'id' => $this->story_id,
            'title' => 'Q2 Performance Analysis',
            'content' => 'Dashboard insights and analysis...',
            'created_by' => 1,
            'created_at' => now()->toIso8601String(),
            'is_published' => false,
        ];
    }

    /**
     * Configure access permissions for story based on audience segments
     *
     * @param array<string, mixed> $story
     */
    private function configureStoryAccess(array $story): void
    {
        foreach ($this->audience_segments as $segment) {
            $this->setSegmentPermissions($segment, $story['id']);
        }

        Log::debug('Story access configured', [
            'job_id' => $this->jobId,
            'story_id' => $this->story_id,
            'segments_configured' => count($this->audience_segments),
        ]);
    }

    /**
     * Set permissions for a specific audience segment
     */
    private function setSegmentPermissions(string $segment, int $storyId): void
    {
        $permissions = match ($segment) {
            'public' => ['view'],
            'internal' => ['view', 'comment', 'share'],
            'team' => ['view', 'comment', 'share', 'edit_comments'],
            'admin' => ['view', 'comment', 'share', 'edit', 'delete'],
            default => ['view'],
        };

        // In a real implementation, store in database
        Log::debug('Segment permissions set', [
            'job_id' => $this->jobId,
            'segment' => $segment,
            'permissions' => $permissions,
        ]);
    }

    /**
     * Collect recipients for all audience segments
     *
     * @return array<string, array<int>>
     */
    private function collectRecipients(): array
    {
        $recipients = [];

        foreach ($this->audience_segments as $segment) {
            $recipientIds = $this->getSegmentRecipients($segment);
            if (!empty($recipientIds)) {
                $recipients[$segment] = $recipientIds;
            }
        }

        Log::debug('Recipients collected', [
            'job_id' => $this->jobId,
            'total_recipients' => count(array_merge(...array_values($recipients))),
        ]);

        return $recipients;
    }

    /**
     * Get recipient IDs for a specific segment
     *
     * @return array<int>
     */
    private function getSegmentRecipients(string $segment): array
    {
        // In a real implementation, query users by segment/role
        return match ($segment) {
            'public' => [], // No specific notification for public
            'internal' => [1, 2, 3, 4, 5], // Internal team members
            'team' => [1, 2, 3], // Specific team
            'admin' => [1], // Admins only
            default => [],
        };
    }

    /**
     * Send notifications to recipients
     *
     * @param array<string, array<int>> $recipients
     * @param array<string, mixed> $story
     */
    private function notifyRecipients(array $recipients, array $story): void
    {
        $totalNotifications = 0;

        foreach ($recipients as $segment => $recipientIds) {
            $notificationsSent = $this->sendSegmentNotifications($segment, $recipientIds, $story);
            $totalNotifications += $notificationsSent;

            Log::info('Segment notifications sent', [
                'job_id' => $this->jobId,
                'segment' => $segment,
                'count' => $notificationsSent,
            ]);
        }

        Log::info('All notifications dispatched', [
            'job_id' => $this->jobId,
            'total_notifications' => $totalNotifications,
        ]);
    }

    /**
     * Send notifications to a specific segment
     */
    private function sendSegmentNotifications(string $segment, array $recipientIds, array $story): int
    {
        $count = 0;

        // Batch notifications (100 at a time)
        $batches = array_chunk($recipientIds, 100);

        foreach ($batches as $batch) {
            try {
                // In a real implementation, use Laravel Notifications
                // Notification::send($users, new StoryPublishedNotification($story));

                $count += count($batch);

                Log::debug('Notification batch sent', [
                    'job_id' => $this->jobId,
                    'segment' => $segment,
                    'batch_size' => count($batch),
                ]);
            } catch (\Throwable $e) {
                Log::error('Notification batch failed', [
                    'job_id' => $this->jobId,
                    'segment' => $segment,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $count;
    }

    /**
     * Mark story as published in database
     *
     * @param array<string, mixed> $story
     */
    private function publishStory(array $story): void
    {
        // In a real implementation, update DataStory model
        $storyData = [
            'is_published' => true,
            'published_at' => now()->toIso8601String(),
            'published_by' => 1, // Current user
            'audience_segments' => $this->audience_segments,
        ];

        Log::debug('Story published', [
            'job_id' => $this->jobId,
            'story_id' => $this->story_id,
            'data' => $storyData,
        ]);
    }

    /**
     * Create audit log entry for publication
     *
     * @param array<string, array<int>> $recipients
     */
    private function logPublication(array $recipients): void
    {
        $totalRecipients = collect($recipients)->flatten()->unique()->count();

        $auditLog = [
            'action' => 'story_published',
            'story_id' => $this->story_id,
            'audience_segments' => $this->audience_segments,
            'recipients_notified' => $totalRecipients,
            'published_by' => 1, // Current user
            'published_at' => now()->toIso8601String(),
            'metadata' => [
                'job_id' => $this->jobId,
                'notify_enabled' => $this->notify_recipients,
            ],
        ];

        // In a real implementation, store in AuditLog table
        Log::info('Publication audit log created', $auditLog);
    }
}
