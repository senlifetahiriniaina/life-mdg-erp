<?php

declare(strict_types=1);

namespace Modules\HR\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\HR\Models\Employee;
use Modules\HR\Services\EmployeeManagementService;

/**
 * @group HR - Employee Onboarding & Offboarding
 * Distinct from EmployeeController's plain CRUD: adds an onboarding/offboarding
 * workflow (checklist + status transition) around the real Employee model.
 */
class EmployeeManagementController extends Controller
{
    public function __construct(private readonly EmployeeManagementService $service) {}

    public function onboard(Request $request): JsonResponse
    {
        $this->authorize('create', Employee::class);

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:hr_employees,email'],
            'phone' => ['nullable', 'string'],
            'date_of_birth' => ['nullable', 'date'],
            'hire_date' => ['required', 'date'],
            'department_id' => ['required', 'exists:hr_departments,id'],
            'job_position_id' => ['required', 'exists:hr_job_positions,id'],
            'employment_type' => ['nullable', 'string'],
            'manager_id' => ['nullable', 'exists:hr_employees,id'],
        ]);

        // Chantier 32: company_id always derived server-side, never from
        // client input — this is a second, independent real create path for
        // Employee (alongside EmployeeController::store()) that was equally
        // missing it.
        $validated['company_id'] = $request->user()->company_id;

        return response()->json($this->service->onboardEmployee($validated), 201);
    }

    public function completeOnboarding(Employee $employee): JsonResponse
    {
        $this->authorize('update', $employee);

        return response()->json($this->service->completeOnboarding($employee->id));
    }

    public function profile(Employee $employee): JsonResponse
    {
        $this->authorize('view', $employee);

        return response()->json($this->service->getEmployeeProfile($employee->id));
    }

    public function terminate(Request $request, Employee $employee): JsonResponse
    {
        // Reuses 'update' (hr.employee.update) rather than the policy's
        // 'archive' method: hr.employee.archive was never seeded as a real
        // permission (RolesAndPermissionsSeeder only generates view-any/view/
        // create/update/delete per resource) — same workaround already used
        // by EmployeeController::export(), see its comment.
        $this->authorize('update', $employee);

        $validated = $request->validate([
            'reason' => ['required', 'string'],
            'last_work_day' => ['nullable', 'date'],
        ]);

        return response()->json($this->service->terminateEmployee(
            $employee->id,
            $validated['reason'],
            $validated['last_work_day'] ?? null
        ));
    }
}
