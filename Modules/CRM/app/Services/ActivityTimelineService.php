<?php

declare(strict_types=1);

namespace Modules\CRM\Services;

use Illuminate\Support\Collection;
use Modules\CRM\Models\Activity;
use Modules\CRM\Models\Opportunity;
use Modules\CRM\Models\Contact;
use App\Models\User;

class ActivityTimelineService
{
    public const ACTIVITY_TYPES = ['call', 'email', 'meeting', 'task', 'note'];
    public const ACTIVITY_STATUSES = ['pending', 'completed', 'cancelled'];

    /**
     * Create activity record.
     */
    public function createActivity(
        int $userId,
        string $type,
        string $title,
        ?string $description = null,
        ?string $status = 'pending',
        ?string $subjectType = null,
        ?int $subjectId = null,
        ?\DateTime $dueAt = null,
        ?\DateTime $doneAt = null
    ): Activity {
        if (!in_array($type, self::ACTIVITY_TYPES)) {
            throw new \InvalidArgumentException("Invalid activity type: {$type}");
        }

        if ($status && !in_array($status, self::ACTIVITY_STATUSES)) {
            throw new \InvalidArgumentException("Invalid activity status: {$status}");
        }

        return Activity::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'description' => $description,
            'status' => $status ?? 'pending',
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'due_at' => $dueAt,
            'done_at' => $doneAt,
        ]);
    }

    /**
     * Get activity timeline for opportunity.
     */
    public function getOpportunityTimeline(int $opportunityId): Collection
    {
        return Activity::where('subject_type', Opportunity::class)
            ->where('subject_id', $opportunityId)
            ->with('user')
            ->latest('created_at')
            ->get();
    }

    /**
     * Get activity timeline for contact.
     */
    public function getContactTimeline(int $contactId): Collection
    {
        return Activity::where('subject_type', Contact::class)
            ->where('subject_id', $contactId)
            ->with('user')
            ->latest('created_at')
            ->get();
    }

    /**
     * Get timeline filtered by activity type.
     */
    public function getTimelineByType(
        string $subjectType,
        int $subjectId,
        string $type
    ): Collection {
        if (!in_array($type, self::ACTIVITY_TYPES)) {
            throw new \InvalidArgumentException("Invalid activity type: {$type}");
        }

        return Activity::where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->where('type', $type)
            ->with('user')
            ->latest('created_at')
            ->get();
    }

    /**
     * Get timeline filtered by date range.
     */
    public function getTimelineByDateRange(
        string $subjectType,
        int $subjectId,
        \DateTime $startDate,
        \DateTime $endDate
    ): Collection {
        return Activity::where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with('user')
            ->latest('created_at')
            ->get();
    }

    /**
     * Get pending activities for user.
     */
    public function getPendingForUser(int $userId): Collection
    {
        return Activity::where('user_id', $userId)
            ->where('status', 'pending')
            ->with('subject')
            ->oldest('due_at')
            ->get();
    }

    /**
     * Get overdue activities.
     */
    public function getOverdueActivities(): Collection
    {
        return Activity::where('status', 'pending')
            ->where('due_at', '<', now())
            ->with(['user', 'subject'])
            ->oldest('due_at')
            ->get();
    }

    /**
     * Complete activity.
     */
    public function completeActivity(Activity $activity): Activity
    {
        $activity->update([
            'status' => 'completed',
            'done_at' => now(),
        ]);

        return $activity->refresh();
    }

    /**
     * Cancel activity.
     */
    public function cancelActivity(Activity $activity, ?string $reason = null): Activity
    {
        $activity->update([
            'status' => 'cancelled',
            'description' => $reason ? "{$activity->description}\n\nCancelled: {$reason}" : $activity->description,
        ]);

        return $activity->refresh();
    }

    /**
     * Update activity.
     */
    public function updateActivity(
        Activity $activity,
        ?string $title = null,
        ?string $description = null,
        ?string $status = null,
        ?\DateTime $dueAt = null,
        ?\DateTime $doneAt = null
    ): Activity {
        $data = [];

        if ($title !== null) {
            $data['title'] = $title;
        }

        if ($description !== null) {
            $data['description'] = $description;
        }

        if ($status !== null) {
            if (!in_array($status, self::ACTIVITY_STATUSES)) {
                throw new \InvalidArgumentException("Invalid activity status: {$status}");
            }
            $data['status'] = $status;
        }

        if ($dueAt !== null) {
            $data['due_at'] = $dueAt;
        }

        if ($doneAt !== null) {
            $data['done_at'] = $doneAt;
        }

        $activity->update($data);

        return $activity->refresh();
    }

    /**
     * Get activities grouped by type.
     */
    public function getTimelineGroupedByType(
        string $subjectType,
        int $subjectId
    ): array {
        $activities = Activity::where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->get();

        $grouped = [];
        foreach (self::ACTIVITY_TYPES as $type) {
            $grouped[$type] = $activities->where('type', $type)->values();
        }

        return $grouped;
    }

    /**
     * Get timeline summary statistics.
     */
    public function getTimelineSummary(
        string $subjectType,
        int $subjectId
    ): array {
        $activities = Activity::where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->get();

        return [
            'total_activities' => $activities->count(),
            'completed' => $activities->where('status', 'completed')->count(),
            'pending' => $activities->where('status', 'pending')->count(),
            'cancelled' => $activities->where('status', 'cancelled')->count(),
            'by_type' => [
                'calls' => $activities->where('type', 'call')->count(),
                'emails' => $activities->where('type', 'email')->count(),
                'meetings' => $activities->where('type', 'meeting')->count(),
                'tasks' => $activities->where('type', 'task')->count(),
                'notes' => $activities->where('type', 'note')->count(),
            ],
            'last_activity' => $activities->sortByDesc('created_at')->first()?->created_at,
            'activity_frequency' => $activities->count() > 0
                ? round($activities->count() / max(1, now()->diffInDays($activities->min('created_at'))))
                : 0,
        ];
    }
}
