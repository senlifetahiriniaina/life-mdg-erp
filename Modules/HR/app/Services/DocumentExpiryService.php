<?php

declare(strict_types=1);

namespace Modules\HR\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Modules\HR\Models\Employee;
use Modules\HR\Models\EmployeeDocument;

class DocumentExpiryService
{
    /**
     * Return documents expiring within the given number of days.
     *
     * @return Collection<EmployeeDocument>
     */
    // Chantier 32: threaded an optional $companyId through (EmployeeDocument
    // has no company_id column of its own — resolved via the employee it
    // belongs to, same pattern as AttendancePolicy's employee-derived
    // checks) — DocumentAlertController::expiring() now scopes this to the
    // acting user's own company.
    public function getExpiringDocuments(int $days = 30, ?int $companyId = null): Collection
    {
        return EmployeeDocument::with('employee')
            ->expiring($days)
            ->when($companyId !== null, fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('company_id', $companyId)))
            ->orderBy('expiry_date')
            ->get();
    }

    /**
     * Return all documents that are already expired.
     *
     * @return Collection<EmployeeDocument>
     */
    public function getExpiredDocuments(): Collection
    {
        return EmployeeDocument::with('employee')
            ->expired()
            ->orderBy('expiry_date')
            ->get();
    }

    /**
     * Mark expired documents and update their status.
     */
    public function markExpired(): int
    {
        return EmployeeDocument::whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<=', now())
            ->where('status', '!=', 'expired')
            ->update(['status' => 'expired']);
    }

    /**
     * Update status of expiring-soon documents.
     */
    public function refreshStatuses(): void
    {
        // Mark expired
        $this->markExpired();

        // Mark expiring_soon (within 60 days)
        EmployeeDocument::whereNotNull('expiry_date')
            ->whereDate('expiry_date', '>', now())
            ->whereDate('expiry_date', '<=', now()->addDays(60))
            ->where('status', 'valid')
            ->update(['status' => 'expiring_soon']);

        // Restore 'valid' status for docs that are far from expiry
        EmployeeDocument::whereNotNull('expiry_date')
            ->whereDate('expiry_date', '>', now()->addDays(60))
            ->where('status', 'expiring_soon')
            ->update(['status' => 'valid']);
    }

    /**
     * Run daily alert cycle: send alerts at 60/30/7 day thresholds.
     *
     * @return array{sent: int, errors: int}
     */
    public function runDailyAlerts(): array
    {
        $sent   = 0;
        $errors = 0;

        $thresholds = [60, 30, 7];

        foreach ($thresholds as $days) {
            $docs = EmployeeDocument::with('employee.user')
                ->whereNotNull('expiry_date')
                ->whereDate('expiry_date', '>', now())
                ->whereDate('expiry_date', '<=', now()->addDays($days))
                ->where("alert_sent_{$days}", false)
                ->get();

            foreach ($docs as $doc) {
                try {
                    $this->notifyEmployee($doc, $days);
                    $this->notifyManager($doc, $days);

                    $doc->update(["alert_sent_{$days}" => true]);
                    $sent++;
                } catch (\Throwable $e) {
                    Log::warning("DocumentExpiryService: alert failed for document #{$doc->id}", [
                        'error' => $e->getMessage(),
                    ]);
                    $errors++;
                }
            }
        }

        return compact('sent', 'errors');
    }

    /**
     * Build a compliance report grouped by document type and status.
     *
     * @return array{
     *   total: int,
     *   valid: int,
     *   expiring_soon: int,
     *   expired: int,
     *   by_type: array,
     *   by_department: array,
     *   missing_documents: array
     * }
     */
    // Chantier 32: threaded an optional $companyId through — same rationale
    // as getExpiringDocuments() above.
    public function complianceReport(?int $companyId = null): array
    {
        $all = EmployeeDocument::with('employee')
            ->when($companyId !== null, fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('company_id', $companyId)))
            ->get();

        $grouped = $all->groupBy('status');

        $byType = $all->groupBy('document_type')->map(fn ($docs) => [
            'total'        => $docs->count(),
            'expired'      => $docs->where('status', 'expired')->count(),
            'expiring_soon' => $docs->where('status', 'expiring_soon')->count(),
            'valid'        => $docs->where('status', 'valid')->count(),
        ])->toArray();

        $byDepartment = $all->groupBy(fn ($doc) => $doc->employee?->department_id ?? 'unknown')
            ->map(fn ($docs) => [
                'total'        => $docs->count(),
                'expired'      => $docs->where('status', 'expired')->count(),
                'expiring_soon' => $docs->where('status', 'expiring_soon')->count(),
            ])->toArray();

        return [
            'total'             => $all->count(),
            'valid'             => $grouped->get('valid', collect())->count(),
            'expiring_soon'     => $grouped->get('expiring_soon', collect())->count(),
            'expired'           => $grouped->get('expired', collect())->count(),
            'by_type'           => $byType,
            'by_department'     => $byDepartment,
            'generated_at'      => now()->toIso8601String(),
        ];
    }

    // ── Private ────────────────────────────────────────────────────────────

    private function notifyEmployee(EmployeeDocument $doc, int $daysLeft): void
    {
        $employee = $doc->employee;
        if (! $employee?->user_id) {
            return;
        }

        $user = \App\Models\User::find($employee->user_id);
        if (! $user) {
            return;
        }

        if (class_exists(\Modules\HR\Notifications\DocumentExpiryNotification::class)) {
            $user->notify(new \Modules\HR\Notifications\DocumentExpiryNotification($doc, $daysLeft, 'employee'));
        } else {
            // Fallback: log only
            Log::info("DocumentExpiry: employee #{$employee->id} document '{$doc->title}' expires in {$daysLeft} days.");
        }
    }

    private function notifyManager(EmployeeDocument $doc, int $daysLeft): void
    {
        $employee = $doc->employee;
        if (! $employee?->manager_id) {
            return;
        }

        $manager = Employee::find($employee->manager_id);
        if (! $manager?->user_id) {
            return;
        }

        $user = \App\Models\User::find($manager->user_id);
        if (! $user) {
            return;
        }

        if (class_exists(\Modules\HR\Notifications\DocumentExpiryNotification::class)) {
            $user->notify(new \Modules\HR\Notifications\DocumentExpiryNotification($doc, $daysLeft, 'manager'));
        }
    }
}
