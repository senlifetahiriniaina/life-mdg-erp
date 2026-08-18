<?php

namespace Modules\HR\Services;

use Illuminate\Support\Facades\Cache;
use Modules\HR\Models\Employee;

/**
 * Chantier 8.3: onboardEmployee()/getEmployeeProfile() originally referenced
 * Employee columns that don't exist on the real model (department/position/
 * salary — the real columns are department_id/job_position_id, and salary
 * lives on EmployeeCompensation, not Employee) — fixed to match the real
 * schema. updateEmployee()/getEmployeesByDepartment() were dropped entirely:
 * both were 100% redundant with the already-live, already-routed
 * EmployeeController::update()/byDepartment() (the latter itself delegating
 * to HRService::getEmployeesByDepartment()).
 */
class EmployeeManagementService
{
    const CACHE_TTL = 86400;

    /**
     * Onboard new employee
     */
    public function onboardEmployee(array $data): array
    {
        $employee = Employee::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'hire_date' => $data['hire_date'],
            'department_id' => $data['department_id'],
            'job_position_id' => $data['job_position_id'],
            'employment_type' => $data['employment_type'] ?? 'full_time',
            'manager_id' => $data['manager_id'] ?? null,
            'status' => 'onboarding',
        ]);

        // Create onboarding checklist
        $this->createOnboardingChecklist($employee->id);

        $this->clearCache();

        return [
            'employee_id' => $employee->id,
            'name' => "{$employee->first_name} {$employee->last_name}",
            'status' => 'onboarding_started',
            'message' => 'Employee onboarding initiated',
        ];
    }

    /**
     * Complete onboarding
     */
    public function completeOnboarding(int $employeeId): array
    {
        $employee = Employee::findOrFail($employeeId);
        $employee->update(['status' => 'active']);

        $this->clearCache();

        return [
            'employee_id' => $employee->id,
            'status' => 'active',
            'message' => 'Employee onboarding completed',
        ];
    }

    /**
     * Get employee details with full profile
     */
    public function getEmployeeProfile(int $employeeId): ?array
    {
        $cacheKey = "employee:{$employeeId}:profile";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($employeeId) {
            $employee = Employee::with(['department', 'jobPosition'])->findOrFail($employeeId);

            return [
                'id' => $employee->id,
                'full_name' => "{$employee->first_name} {$employee->last_name}",
                'email' => $employee->email,
                'phone' => $employee->phone,
                'date_of_birth' => $employee->date_of_birth,
                'hire_date' => $employee->hire_date,
                'department' => $employee->department?->name,
                'position' => $employee->jobPosition?->title,
                'employment_type' => $employee->employment_type,
                'manager' => $employee->manager_id ? $this->getManagerName($employee->manager_id) : null,
                'status' => $employee->status,
                'tenure_days' => $this->calculateTenure($employee->hire_date),
                'reports_to' => $this->getDirectReports($employee->id),
            ];
        });
    }

    /**
     * Terminate employee
     */
    public function terminateEmployee(int $employeeId, string $reason, ?string $lastWorkDay = null): array
    {
        $employee = Employee::findOrFail($employeeId);

        $employee->update([
            'status' => 'terminated',
            'termination_date' => $lastWorkDay ?? now()->toDateString(),
            'termination_reason' => $reason,
        ]);

        // Create offboarding checklist
        $this->createOffboardingChecklist($employee->id);

        $this->clearCache();

        return [
            'employee_id' => $employee->id,
            'status' => 'terminated',
            'last_work_day' => $lastWorkDay,
            'message' => 'Employee termination initiated',
        ];
    }

    /**
     * Calculate tenure in days
     */
    private function calculateTenure(string $hireDate): int
    {
        return now()->diffInDays($hireDate);
    }

    /**
     * Get manager name
     */
    private function getManagerName(int $managerId): ?string
    {
        $manager = Employee::find($managerId);
        return $manager ? "{$manager->first_name} {$manager->last_name}" : null;
    }

    /**
     * Get direct reports
     */
    private function getDirectReports(int $employeeId): array
    {
        return Employee::with('jobPosition')
            ->where('manager_id', $employeeId)
            ->where('status', 'active')
            ->get()
            ->map(fn($e) => [
                'id' => $e->id,
                'name' => "{$e->first_name} {$e->last_name}",
                'position' => $e->jobPosition?->title,
            ])
            ->toArray();
    }

    /**
     * Create onboarding checklist
     */
    private function createOnboardingChecklist(int $employeeId): void
    {
        $checklist = [
            'Company orientation',
            'IT equipment setup',
            'Office access setup',
            'Benefits enrollment',
            'Policy training',
            'Department onboarding',
        ];

        // Store in database or cache
        Cache::put("onboarding:{$employeeId}", $checklist, self::CACHE_TTL);
    }

    /**
     * Create offboarding checklist
     */
    private function createOffboardingChecklist(int $employeeId): void
    {
        $checklist = [
            'Final paycheck processing',
            'Equipment return',
            'System access removal',
            'Benefits termination',
            'Offboarding interview',
            'Reference documentation',
        ];

        // Store in database or cache
        Cache::put("offboarding:{$employeeId}", $checklist, self::CACHE_TTL);
    }

    /**
     * Clear cache
     */
    private function clearCache(): void
    {
        Cache::tags(['employees'])->flush();
    }
}
