<?php

declare(strict_types=1);

namespace Modules\Accounting\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Modules\Accounting\Models\RevenueContract;
use Modules\Accounting\Models\RevenueRecognitionEvent;

class ASC606RevenueRecognitionService
{
    public const RECOGNITION_METHOD_TIME_BASED = 'time_based';
    public const RECOGNITION_METHOD_MILESTONE = 'milestone';
    public const RECOGNITION_METHOD_UNITS_DELIVERED = 'units_delivered';
    public const RECOGNITION_METHOD_PERCENTAGE_OF_COMPLETION = 'percentage_of_completion';

    public function createContract(array $data): RevenueContract
    {
        $data['status'] = 'active';
        $data['cumulative_revenue_recognized'] = 0;

        return RevenueContract::create($data);
    }

    public function updateContract(RevenueContract $contract, array $data): RevenueContract
    {
        $contract->update($data);

        return $contract->fresh();
    }

    public function recognizeRevenueTimeBased(
        RevenueContract|array $contract,
        Carbon|string|null $asOfDate = null,
        string|null $currentDate = null,
        int|null $daysInContract = null
    ): float {
        if (is_array($contract)) {
            $amount = (float) ($contract['contract_amount'] ?? 0);
            $start = Carbon::parse($contract['contract_start_date']);
            $end = Carbon::parse($contract['contract_end_date']);
            $current = Carbon::parse($currentDate ?? $asOfDate ?? now());
            $totalDays = $daysInContract ?? max(1, $start->diffInDays($end));
            $elapsed = min($totalDays, $start->diffInDays($current));
            return round($amount * ($elapsed / $totalDays), 2);
        }

        $asOfDate = $asOfDate instanceof Carbon ? $asOfDate : Carbon::parse($asOfDate ?? now());
        $totalDays = $contract->contract_end_date->diffInDays($contract->contract_start_date);

        if ($totalDays === 0) {
            return 0;
        }

        $elapsedDays = $asOfDate->diffInDays($contract->contract_start_date);
        $percentageComplete = min(100, ($elapsedDays / $totalDays) * 100);
        $revenueToRecognize = ((float) $contract->contract_amount * $percentageComplete / 100) - (float) $contract->cumulative_revenue_recognized;

        if ($revenueToRecognize > 0) {
            $event = $this->recordRevenueRecognitionEvent($contract, $revenueToRecognize, 'time_based', $asOfDate);
            $event->recognize();
        }

        return max(0, $revenueToRecognize);
    }

    public function recognizeRevenuePercentageOfCompletion(
        RevenueContract|array $contract,
        float|int|null $percentageComplete = null,
        Carbon|null $asOfDate = null,
        float|int|null $costIncurred = null
    ): float {
        if (is_array($contract)) {
            $amount = (float) ($contract['contract_amount'] ?? 0);
            $totalCosts = (float) ($contract['total_costs'] ?? 0);
            if ($costIncurred !== null && $totalCosts > 0) {
                $pct = min(100, ($costIncurred / $totalCosts) * 100);
            } else {
                $pct = min(100, max(0, (float) ($percentageComplete ?? 0)));
            }
            return round($amount * $pct / 100, 2);
        }

        $percentageComplete = min(100, max(0, (float) ($percentageComplete ?? 0)));
        $revenueToRecognize = ((float) $contract->contract_amount * $percentageComplete / 100) - (float) $contract->cumulative_revenue_recognized;

        if ($revenueToRecognize > 0) {
            $event = $this->recordRevenueRecognitionEvent($contract, $revenueToRecognize, 'percentage_of_completion', $asOfDate ?? now());
            $event->recognize();
        }

        return max(0, $revenueToRecognize);
    }

    public function recognizeRevenueByMilestone(
        RevenueContract|array $contract,
        string|array|null $milestone = null,
        float|null $milestoneAmount = null,
        Carbon|null $asOfDate = null,
        array|null $milestones = null
    ): RevenueRecognitionEvent|float {
        if (is_array($contract)) {
            $milestonesData = $milestones ?? (is_array($milestone) ? $milestone : []);
            $total = 0.0;
            foreach ($milestonesData as $m) {
                if (!empty($m['completed'])) {
                    $total += (float) ($m['amount'] ?? 0);
                }
            }
            return $total;
        }

        $event = $this->recordRevenueRecognitionEvent(
            $contract,
            (float) ($milestoneAmount ?? 0),
            'milestone_' . ($milestone ?? 'unknown'),
            $asOfDate ?? now()
        );

        $event->recognize();

        return $event;
    }

    public function recognizeRevenueByUnitsDelivered(
        RevenueContract|array $contract,
        int $unitsDelivered,
        float|null $revenuePerUnit = null,
        Carbon|null $asOfDate = null
    ): float {
        if (is_array($contract)) {
            $amount = (float) ($contract['contract_amount'] ?? 0);
            $totalUnits = (int) ($contract['total_units'] ?? 1);
            return $totalUnits > 0 ? round($amount * ($unitsDelivered / $totalUnits), 2) : 0.0;
        }

        $revenueToRecognize = $unitsDelivered * (float) ($revenuePerUnit ?? 0);

        $event = $this->recordRevenueRecognitionEvent(
            $contract,
            $revenueToRecognize,
            'units_delivered',
            $asOfDate ?? now(),
            "Units delivered: $unitsDelivered at " . $revenuePerUnit . ' per unit'
        );

        $event->recognize();

        return $revenueToRecognize;
    }

    public function recordRevenueRecognitionEvent(
        RevenueContract $contract,
        float $revenueAmount,
        string $recognitionBasis,
        Carbon $recognitionDate,
        ?string $notes = null
    ): RevenueRecognitionEvent {
        return RevenueRecognitionEvent::create([
            'revenue_contract_id' => $contract->id,
            'revenue_amount' => $revenueAmount,
            'recognition_basis' => $recognitionBasis,
            'recognition_date' => $recognitionDate,
            'status' => 'pending',
            'notes' => $notes,
        ]);
    }

    public function getContractStatus(RevenueContract $contract): array
    {
        return [
            'contract_id' => $contract->id,
            'contract_number' => $contract->contract_number,
            'contract_amount' => (float) $contract->contract_amount,
            'cumulative_revenue_recognized' => (float) $contract->cumulative_revenue_recognized,
            'remaining_revenue' => $contract->remainingRevenue(),
            'percentage_complete' => $contract->percentageComplete(),
            'is_complete' => $contract->isComplete(),
            'recognition_events_count' => $contract->recognitionEvents()->count(),
            'last_recognition_date' => $contract->recognitionEvents()
                ->orderByDesc('recognition_date')
                ->first()?->recognition_date,
        ];
    }

    public function getRevenueSchedule(RevenueContract $contract, Carbon $startDate, Carbon $endDate): array
    {
        $events = $contract->recognitionEvents()
            ->whereBetween('recognition_date', [$startDate, $endDate])
            ->orderBy('recognition_date')
            ->get();

        $schedule = [];
        foreach ($events as $event) {
            $dateKey = $event->recognition_date->toDateString();
            if (!isset($schedule[$dateKey])) {
                $schedule[$dateKey] = 0;
            }
            $schedule[$dateKey] += (float) $event->revenue_amount;
        }

        return $schedule;
    }

    public function getContractsByStatus(string $status): Collection
    {
        return RevenueContract::where('status', $status)
            ->with('recognitionEvents')
            ->get();
    }

    public function validateRemainingRevenue(RevenueContract $contract, float $proposedRevenue): bool
    {
        $remaining = $contract->remainingRevenue();

        return $proposedRevenue <= $remaining + 0.01;
    }

    public function calculatePercentageOfCompletion(
        float $costIncurred,
        float $totalEstimatedCost
    ): float {
        if ($totalEstimatedCost === 0) {
            return 0;
        }

        return min(100, ($costIncurred / $totalEstimatedCost) * 100);
    }

    public function generateRevenueRecognitionJournal(RevenueRecognitionEvent $event): array
    {
        return [
            'description' => "Revenue recognition for contract {$event->contract->contract_number}",
            'debit_account' => 'accounts_receivable',
            'debit_amount' => (float) $event->revenue_amount,
            'credit_account' => 'revenue',
            'credit_amount' => (float) $event->revenue_amount,
            'date' => $event->recognition_date,
            'reference' => "REV-REC-{$event->contract->id}-{$event->id}",
        ];
    }

    public function processMonthEndRevenueRecognition(
        Carbon|string|null $monthEnd = null,
        array|null $contracts = null,
        string|null $currentMonth = null
    ): array {
        if ($contracts !== null) {
            $currentDate = $currentMonth ? Carbon::parse($currentMonth . '-01')->endOfMonth() : now();
            $totalRevenue = 0.0;
            $processed = [];
            foreach ($contracts as $contract) {
                $method = $contract['revenue_recognition_method'] ?? 'time_based';
                if ($method === 'time_based') {
                    $amount = $this->recognizeRevenueTimeBased(
                        $contract,
                        currentDate: $currentDate->toDateString()
                    );
                } elseif ($method === 'units_delivered') {
                    $totalUnits = (int) ($contract['total_units'] ?? 1);
                    $unitsDelivered = (int) ($contract['units_delivered'] ?? 0);
                    $revenuePerUnit = $totalUnits > 0
                        ? (float) $contract['contract_amount'] / $totalUnits
                        : 0.0;
                    $amount = $this->recognizeRevenueByUnitsDelivered($contract, $unitsDelivered, $revenuePerUnit);
                } else {
                    $amount = 0.0;
                }
                if ($amount > 0) {
                    $processed[] = ['contract_id' => $contract['id'] ?? null, 'recognized_amount' => $amount];
                    $totalRevenue += $amount;
                }
            }
            return [
                'total_processed' => count($processed),
                'total_revenue_recognized' => $totalRevenue,
                'processed' => $processed,
            ];
        }

        $monthEndDate = $monthEnd instanceof Carbon ? $monthEnd : Carbon::parse($monthEnd ?? now());
        $dbContracts = RevenueContract::where('status', 'active')
            ->where('revenue_recognition_method', self::RECOGNITION_METHOD_TIME_BASED)
            ->get();

        $processed = [];
        foreach ($dbContracts as $contract) {
            $amount = $this->recognizeRevenueTimeBased($contract, $monthEndDate);
            if ($amount > 0) {
                $processed[] = [
                    'contract_id' => $contract->id,
                    'recognized_amount' => $amount,
                ];
            }
        }

        return $processed;
    }

    // ─── Additional methods required by tests (array-based API) ──────────────

    /**
     * Returns remaining revenue for a contract (array form).
     *
     * @param array|RevenueContract $contract
     */
    public function remainingRevenue(array|RevenueContract $contract): float
    {
        if ($contract instanceof RevenueContract) {
            $total = (float) $contract->contract_amount;
            $recognized = (float) ($contract->cumulative_revenue_recognized ?? 0);
        } else {
            $total = (float) ($contract['contract_amount'] ?? 0);
            $recognized = (float) ($contract['cumulative_revenue_recognized'] ?? 0);
        }

        return max(0.0, round($total - $recognized, 2));
    }

    /**
     * Returns percentage of completion for a contract.
     *
     * @param array|RevenueContract $contract
     */
    public function percentageComplete(array|RevenueContract $contract): float
    {
        if ($contract instanceof RevenueContract) {
            $total = (float) $contract->contract_amount;
            $recognized = (float) ($contract->cumulative_revenue_recognized ?? 0);
        } else {
            $total = (float) ($contract['contract_amount'] ?? 0);
            $recognized = (float) ($contract['cumulative_revenue_recognized'] ?? 0);
        }

        if ($total <= 0) return 0.0;
        return round(($recognized / $total) * 100, 2);
    }
}
