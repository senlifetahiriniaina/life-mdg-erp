<?php

declare(strict_types=1);

namespace Modules\Projects\Services;

use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Modules\Projects\Models\ResourceAllocation;
use Modules\Projects\Models\ResourceCapacity;

class ResourceCapacityService
{
    /**
     * Allocate a resource to a project/task.
     * Throws a ValidationException when the user would be overallocated (>100%) on any day.
     */
    public function allocate(int $userId, int $projectId, array $data): ResourceAllocation
    {
        $from = Carbon::parse($data['start_date']);
        $to = Carbon::parse($data['end_date']);

        $check = $this->checkOverallocation($userId, $from, $to, $data['allocation_percent'] ?? 100);

        if ($check['overallocated']) {
            throw ValidationException::withMessages([
                'allocation_percent' => 'User would be overallocated during the requested period.',
            ]);
        }

        return ResourceAllocation::create(array_merge(['status' => 'planned'], $data, [
            'user_id' => $userId,
            'project_id' => $projectId,
        ]));
    }

    /**
     * Check whether a user is overallocated in a date range.
     * Optionally include a prospective additional allocation_percent.
     *
     * @return array{overallocated: bool, max_allocation: float, conflicts: array<int, mixed>}
     */
    public function checkOverallocation(
        int $userId,
        Carbon $from,
        Carbon $to,
        int $additionalPercent = 0
    ): array {
        $allocations = ResourceAllocation::where('user_id', $userId)
            ->whereIn('status', ['planned', 'confirmed'])
            ->where('start_date', '<=', $to->toDateString())
            ->where('end_date', '>=', $from->toDateString())
            ->get();

        // Sum per-day allocation across all overlapping allocations
        $maxAllocation = 0.0;
        $conflicts = [];

        foreach (CarbonPeriod::create($from, $to) as $day) {
            $dayTotal = $additionalPercent;

            foreach ($allocations as $allocation) {
                if ($allocation->isOverlapping($day, $day)) {
                    $dayTotal += $allocation->allocation_percent;
                }
            }

            if ($dayTotal > 100) {
                $maxAllocation = max($maxAllocation, (float) $dayTotal);
                $conflicts[] = [
                    'date' => $day->toDateString(),
                    'total_allocation' => $dayTotal,
                ];
            }
        }

        return [
            'overallocated' => count($conflicts) > 0,
            'max_allocation' => $maxAllocation,
            'conflicts' => $conflicts,
        ];
    }

    /**
     * Utilisation report for all users with allocations in the given range.
     *
     * @return array<int, array{name: string, total_planned_hours: float, actual_hours: float, utilization_pct: float, allocations: array<int, mixed>}>
     */
    public function utilizationReport(Carbon $from, Carbon $to): array
    {
        $allocations = ResourceAllocation::with('user')
            ->where('start_date', '<=', $to->toDateString())
            ->where('end_date', '>=', $from->toDateString())
            ->get()
            ->groupBy('user_id');

        $report = [];

        foreach ($allocations as $userId => $userAllocations) {
            /** @var Collection<int, ResourceAllocation> $userAllocations */
            $totalPlanned = 0.0;
            $actualHours = 0.0;
            $allocs = [];

            foreach ($userAllocations as $allocation) {
                $totalPlanned += $allocation->totalPlannedHours();
                $actualHours += (float) $allocation->actual_hours_logged;
                $allocs[] = [
                    'id' => $allocation->id,
                    'project_id' => $allocation->project_id,
                    'allocation_percent' => $allocation->allocation_percent,
                    'start_date' => $allocation->start_date->toDateString(),
                    'end_date' => $allocation->end_date->toDateString(),
                    'status' => $allocation->status,
                ];
            }

            $utilizationPct = $totalPlanned > 0 ? ($actualHours / $totalPlanned) * 100 : 0.0;
            $user = $userAllocations->first()->user;

            $report[] = [
                'user_id' => $userId,
                'name' => $user ? $user->name : "User #{$userId}",
                'total_planned_hours' => round($totalPlanned, 2),
                'actual_hours' => round($actualHours, 2),
                'utilization_pct' => round($utilizationPct, 2),
                'allocations' => $allocs,
            ];
        }

        return $report;
    }

    /**
     * Get day-by-day remaining availability for a user (available_hours - allocated hours for that day).
     *
     * @return array<string, float> keyed by date string
     */
    public function getAvailability(int $userId, Carbon $from, Carbon $to): array
    {
        // Load capacity overrides
        $capacityRecords = ResourceCapacity::where('user_id', $userId)
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString())
            ->get()
            ->keyBy(fn ($r) => $r->date->toDateString());

        // Load active/planned allocations
        $allocations = ResourceAllocation::where('user_id', $userId)
            ->whereIn('status', ['planned', 'confirmed'])
            ->where('start_date', '<=', $to->toDateString())
            ->where('end_date', '>=', $from->toDateString())
            ->get();

        $result = [];

        foreach (CarbonPeriod::create($from, $to) as $day) {
            $dateStr = $day->toDateString();

            /** @var ResourceCapacity|null $capacityRecord */
            $capacityRecord = $capacityRecords[$dateStr] ?? null;
            $available = $capacityRecord ? $capacityRecord->effectiveHours() : 8.0;

            // Deduct allocated hours for this day
            foreach ($allocations as $allocation) {
                if ($allocation->isOverlapping($day, $day)) {
                    $allocated = (float) $allocation->hours_per_day * ($allocation->allocation_percent / 100);
                    $available -= $allocated;
                }
            }

            $result[$dateStr] = round(max(0.0, $available), 2);
        }

        return $result;
    }

    /**
     * Mark days as leave or holiday, creating/updating ResourceCapacity records.
     *
     * @return array<int, ResourceCapacity>
     */
    public function setLeave(int $userId, Carbon $from, Carbon $to, bool $isHoliday = false): array
    {
        $records = [];

        foreach (CarbonPeriod::create($from, $to) as $day) {
            $record = ResourceCapacity::where('user_id', $userId)
                ->whereDate('date', $day->toDateString())
                ->first();

            if ($record) {
                $record->update([
                    'available_hours' => 0.0,
                    'is_holiday' => $isHoliday,
                    'is_leave' => ! $isHoliday,
                ]);
            } else {
                $record = ResourceCapacity::create([
                    'user_id' => $userId,
                    'date' => $day->toDateString(),
                    'available_hours' => 0.0,
                    'is_holiday' => $isHoliday,
                    'is_leave' => ! $isHoliday,
                ]);
            }

            $records[] = $record;
        }

        return $records;
    }

    /**
     * All allocations for a project within the date range.
     *
     * @return array<int, mixed>
     */
    public function getProjectDemand(int $projectId, Carbon $from, Carbon $to): array
    {
        return ResourceAllocation::with('user', 'task')
            ->where('project_id', $projectId)
            ->where('start_date', '<=', $to->toDateString())
            ->where('end_date', '>=', $from->toDateString())
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'project_id' => $a->project_id,
                'user_id' => $a->user_id,
                'user_name' => $a->user?->name,
                'task_id' => $a->task_id,
                'allocation_type' => $a->allocation_type,
                'allocation_percent' => $a->allocation_percent,
                'start_date' => $a->start_date->toDateString(),
                'end_date' => $a->end_date->toDateString(),
                'hours_per_day' => $a->hours_per_day,
                'actual_hours_logged' => $a->actual_hours_logged,
                'status' => $a->status,
                'total_planned_hours' => $a->totalPlannedHours(),
            ])
            ->values()
            ->all();
    }

    /**
     * Suggest the least-allocated users for a project's requirements.
     *
     * $requirements: [['role' => '...', 'hours' => 40, 'start_date' => '...', 'end_date' => '...'], ...]
     *
     * @param  array<int, array{hours?: int, start_date?: string, end_date?: string}>  $requirements
     * @return array<int, mixed>
     */
    public function suggestAllocations(int $projectId, array $requirements): array
    {
        $suggestions = [];

        foreach ($requirements as $req) {
            $from = Carbon::parse($req['start_date'] ?? now()->toDateString());
            $to = Carbon::parse($req['end_date'] ?? now()->addDays(30)->toDateString());
            $hours = (int) ($req['hours'] ?? 40);

            // Get all users sorted by current total allocation in the range (ascending)
            $users = User::all();

            $scored = $users->map(function (User $user) use ($from, $to) {
                $totalAllocation = ResourceAllocation::where('user_id', $user->id)
                    ->whereIn('status', ['planned', 'confirmed'])
                    ->where('start_date', '<=', $to->toDateString())
                    ->where('end_date', '>=', $from->toDateString())
                    ->sum('allocation_percent');

                return ['user' => $user, 'total_allocation' => (int) $totalAllocation];
            })->sortBy('total_allocation');

            $suggestion = [];
            foreach ($scored->take(3) as $entry) {
                $suggestion[] = [
                    'user_id' => $entry['user']->id,
                    'user_name' => $entry['user']->name,
                    'current_allocation' => $entry['total_allocation'],
                    'available_capacity' => max(0, 100 - $entry['total_allocation']),
                    'required_hours' => $hours,
                    'start_date' => $from->toDateString(),
                    'end_date' => $to->toDateString(),
                ];
            }

            $suggestions[] = [
                'requirement' => $req,
                'candidates' => $suggestion,
            ];
        }

        return $suggestions;
    }
}
