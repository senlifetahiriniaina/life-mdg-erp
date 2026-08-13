<?php

declare(strict_types=1);

namespace Modules\Projects\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Modules\Projects\Models\ProjectBilling;
use Modules\Projects\Models\TimeEntry;

class TimeTrackingService
{
    public function startTimer(int $projectId, int $userId, array $data = []): TimeEntry
    {
        return TimeEntry::create(array_merge($data, [
            'project_id' => $projectId,
            'user_id' => $userId,
            'started_at' => now(),
            'ended_at' => null,
        ]));
    }

    public function stopTimer(TimeEntry $entry): TimeEntry
    {
        $entry->stop();

        return $entry->fresh();
    }

    public function logTime(int $projectId, int $userId, array $data): TimeEntry
    {
        $startedAt = Carbon::parse($data['started_at']);
        $endedAt = Carbon::parse($data['ended_at']);

        $durationMinutes = (int) ceil($startedAt->diffInSeconds($endedAt) / 60);

        return TimeEntry::create(array_merge($data, [
            'project_id' => $projectId,
            'user_id' => $userId,
            'duration_minutes' => $durationMinutes,
        ]));
    }

    public function getProjectTimeEntries(int $projectId): Collection
    {
        return TimeEntry::where('project_id', $projectId)->get();
    }

    public function getUserTimeEntries(int $userId): Collection
    {
        return TimeEntry::where('user_id', $userId)->get();
    }

    public function getProjectBillableHours(int $projectId): float
    {
        return TimeEntry::where('project_id', $projectId)
            ->where('billable', true)
            ->where('billed', false)
            ->get()
            ->sum(fn (TimeEntry $entry) => $entry->durationInHours());
    }

    public function getBillableAmount(int $projectId): float
    {
        return TimeEntry::where('project_id', $projectId)
            ->where('billable', true)
            ->where('billed', false)
            ->get()
            ->sum(fn (TimeEntry $entry) => (float) $entry->hourly_rate * $entry->durationInHours());
    }

    public function markEntriesAsBilled(int $projectId): int
    {
        $entries = TimeEntry::where('project_id', $projectId)
            ->where('billable', true)
            ->where('billed', false)
            ->get();

        foreach ($entries as $entry) {
            $entry->markBilled();
        }

        return $entries->count();
    }

    public function setupProjectBilling(int $projectId, array $data): ProjectBilling
    {
        return ProjectBilling::updateOrCreate(
            ['project_id' => $projectId],
            $data
        );
    }

    public function getProjectBillingStats(int $projectId): array
    {
        $entries = TimeEntry::where('project_id', $projectId)->get();

        $totalHours = $entries->sum(fn (TimeEntry $e) => $e->durationInHours());

        $billableEntries = $entries->filter(fn (TimeEntry $e) => $e->isBillable());
        $billableHours = $billableEntries->sum(fn (TimeEntry $e) => $e->durationInHours());

        $billableAmount = $billableEntries->sum(fn (TimeEntry $e) => $e->durationInHours() * (float) $e->hourly_rate);

        $billedEntries = $billableEntries->filter(fn (TimeEntry $e) => $e->isBilled());
        $billedAmount = $billedEntries->sum(fn (TimeEntry $e) => $e->durationInHours() * (float) $e->hourly_rate);

        $unbilledAmount = $billableAmount - $billedAmount;

        return [
            'total_hours' => $totalHours,
            'billable_hours' => $billableHours,
            'billable_amount' => $billableAmount,
            'billed_amount' => $billedAmount,
            'unbilled_amount' => $unbilledAmount,
        ];
    }
}
