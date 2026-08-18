<?php

declare(strict_types=1);

namespace Modules\Projects\Services;

use Illuminate\Support\Facades\DB;
use Modules\Projects\Models\TimeEntry;

/**
 * Service for deduplicating time tracking entries and ensuring single source of truth.
 * Links Projects module time entries to Timesheets module as authoritative source.
 */
class TimeTrackingDeduplicationService
{
    /**
     * Find and remove duplicate time entries for a user on a date.
     *
     * @param int $userId
     * @param string $date YYYY-MM-DD format
     * @return array {duplicates_removed: int, entries_merged: int}
     */
    public function deduplicateUserDate(int $userId, string $date): array
    {
        $result = ['duplicates_removed' => 0, 'entries_merged' => 0];

        // Group entries by project_id and task_id on the same date
        $grouped = TimeEntry::where('user_id', $userId)
            ->whereDate('started_at', $date)
            ->get()
            ->groupBy(function ($entry) {
                return "{$entry->project_id}_{$entry->task_id}";
            });

        foreach ($grouped as $group) {
            if ($group->count() <= 1) {
                continue;
            }

            // Found duplicates - merge them
            $result['entries_merged'] += $group->count();
            $merged = $this->mergeTimeEntries($group->all());
            $result['duplicates_removed'] += $group->count() - 1;
        }

        return $result;
    }

    /**
     * Merge multiple time entries into one, summing durations.
     * Keeps the earliest entry and deletes the rest.
     *
     * @param array $entries TimeEntry models
     * @return TimeEntry The merged entry
     */
    private function mergeTimeEntries(array $entries): TimeEntry
    {
        if (empty($entries)) {
            throw new \InvalidArgumentException('Cannot merge empty array of entries');
        }

        // Sort by created_at to keep the earliest
        usort($entries, function ($a, $b) {
            return $a->created_at <=> $b->created_at;
        });

        $primary = $entries[0]; // Keep this one
        $totalDuration = 0;

        foreach ($entries as $entry) {
            $totalDuration += $entry->duration_minutes ?? 0;
        }

        // Update primary with merged duration
        $primary->update([
            'duration_minutes' => $totalDuration,
        ]);

        // Delete duplicates
        for ($i = 1; $i < count($entries); $i++) {
            $entries[$i]->delete();
        }

        return $primary->fresh();
    }

    /**
     * Check if Timesheets module exists and sync is enabled.
     *
     * @return bool
     */
    public function isTimesheetsSyncEnabled(): bool
    {
        if (!class_exists('Modules\Timesheets\Models\TimesheetEntry')) {
            return false;
        }

        return config('projects.timesheets_sync_enabled', true);
    }

    /**
     * Sync a time entry from Projects to Timesheets (single source of truth).
     * Creates or updates a corresponding timesheet entry.
     *
     * @param TimeEntry $entry
     * @return bool
     */
    public function syncToTimesheet(TimeEntry $entry): bool
    {
        if (!$this->isTimesheetsSyncEnabled()) {
            return false;
        }

        try {
            $timesheetModel = 'Modules\Timesheets\Models\TimesheetEntry';
            if (!class_exists($timesheetModel)) {
                return false;
            }

            // Find or create timesheet entry
            $timesheetEntry = $timesheetModel::firstOrCreate(
                [
                    'user_id' => $entry->user_id,
                    'project_id' => $entry->project_id,
                    'task_id' => $entry->task_id,
                    'date' => $entry->started_at->format('Y-m-d'),
                ],
                [
                    'duration_minutes' => $entry->duration_minutes ?? 0,
                    'description' => $entry->description ?? '',
                    'billable' => $entry->billable ?? false,
                    'source_module' => 'Projects',
                ]
            );

            // Update if it already exists
            if ($timesheetEntry->wasRecentlyCreated === false) {
                $timesheetEntry->update([
                    'duration_minutes' => $entry->duration_minutes ?? 0,
                    'description' => $entry->description ?? '',
                    'billable' => $entry->billable ?? false,
                ]);
            }

            // Update the Projects entry with reference to Timesheet
            $entry->update([
                'timesheet_entry_id' => $timesheetEntry->id ?? null,
                'source' => 'projects',
            ]);

            return true;
        } catch (\Exception $e) {
            \Log::error('Failed to sync time entry to timesheet: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Sync a time entry from Timesheets back to Projects.
     * Ensures Projects reflects the authoritative Timesheet data.
     *
     * @param int $timesheetEntryId
     * @return bool
     */
    public function syncFromTimesheet(int $timesheetEntryId): bool
    {
        if (!$this->isTimesheetsSyncEnabled()) {
            return false;
        }

        try {
            $timesheetModel = 'Modules\Timesheets\Models\TimesheetEntry';
            if (!class_exists($timesheetModel)) {
                return false;
            }

            $timesheetEntry = $timesheetModel::find($timesheetEntryId);
            if (!$timesheetEntry) {
                return false;
            }

            // Find or create Projects time entry
            $projectsEntry = TimeEntry::firstOrCreate(
                [
                    'user_id' => $timesheetEntry->user_id,
                    'project_id' => $timesheetEntry->project_id,
                    'task_id' => $timesheetEntry->task_id,
                    'timesheet_entry_id' => $timesheetEntryId,
                ],
                [
                    'duration_minutes' => $timesheetEntry->duration_minutes,
                    'description' => $timesheetEntry->description ?? '',
                    'billable' => $timesheetEntry->billable ?? false,
                    'started_at' => $timesheetEntry->date,
                    'ended_at' => $timesheetEntry->date,
                    'source' => 'timesheets',
                ]
            );

            // Update if exists
            if ($projectsEntry->wasRecentlyCreated === false) {
                $projectsEntry->update([
                    'duration_minutes' => $timesheetEntry->duration_minutes,
                    'description' => $timesheetEntry->description ?? '',
                    'billable' => $timesheetEntry->billable ?? false,
                ]);
            }

            return true;
        } catch (\Exception $e) {
            \Log::error('Failed to sync timesheet entry to Projects: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Bidirectional sync: ensure Projects and Timesheets are in sync.
     * Authoritative source is Timesheets module.
     *
     * @return array {synced: int, conflicts: int}
     */
    public function fullBidirectionalSync(): array
    {
        if (!$this->isTimesheetsSyncEnabled()) {
            return ['synced' => 0, 'conflicts' => 0];
        }

        $synced = 0;
        $conflicts = 0;

        try {
            $timesheetModel = 'Modules\Timesheets\Models\TimesheetEntry';
            if (!class_exists($timesheetModel)) {
                return ['synced' => 0, 'conflicts' => 0];
            }

            // Sync all Timesheet entries to Projects
            $timesheetEntries = $timesheetModel::all();
            foreach ($timesheetEntries as $te) {
                if ($this->syncFromTimesheet($te->id)) {
                    $synced++;
                } else {
                    $conflicts++;
                }
            }

            // Sync Projects entries that have no Timesheet equivalent
            $orphanedEntries = TimeEntry::whereNull('timesheet_entry_id')
                ->where('source', '!=', 'timesheets')
                ->get();

            foreach ($orphanedEntries as $entry) {
                if ($this->syncToTimesheet($entry)) {
                    $synced++;
                } else {
                    $conflicts++;
                }
            }

            return ['synced' => $synced, 'conflicts' => $conflicts];
        } catch (\Exception $e) {
            \Log::error('Bidirectional sync failed: ' . $e->getMessage());
            return ['synced' => $synced, 'conflicts' => $conflicts];
        }
    }

    /**
     * Audit time entries for consistency.
     * Returns list of inconsistencies found.
     *
     * @return array
     */
    public function auditTimeEntries(): array
    {
        $issues = [];

        // Check for missing duration_minutes
        $missingDuration = TimeEntry::whereNull('duration_minutes')
            ->where(function ($q) {
                $q->whereNotNull('started_at')
                    ->whereNotNull('ended_at');
            })
            ->count();

        if ($missingDuration > 0) {
            $issues[] = [
                'type' => 'missing_duration',
                'count' => $missingDuration,
                'message' => "Found $missingDuration entries with missing duration despite having start/end times",
            ];
        }

        // Check for orphaned entries (no corresponding timesheet)
        if ($this->isTimesheetsSyncEnabled()) {
            $orphaned = TimeEntry::whereNull('timesheet_entry_id')
                ->where('source', '!=', 'timesheets')
                ->count();

            if ($orphaned > 0) {
                $issues[] = [
                    'type' => 'orphaned_entries',
                    'count' => $orphaned,
                    'message' => "Found $orphaned entries not synced to Timesheets",
                ];
            }
        }

        // Check for negative durations
        $negative = TimeEntry::where('duration_minutes', '<', 0)->count();
        if ($negative > 0) {
            $issues[] = [
                'type' => 'negative_duration',
                'count' => $negative,
                'message' => "Found $negative entries with negative duration",
            ];
        }

        return $issues;
    }
}
