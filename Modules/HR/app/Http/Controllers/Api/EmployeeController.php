<?php

namespace Modules\HR\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\HR\Http\Requests\StoreEmployeeRequest;
use Modules\HR\Http\Requests\UpdateEmployeeRequest;
use Modules\HR\Http\Resources\EmployeeResource;
use Modules\HR\Models\Employee;
use Modules\HR\Services\HRService;

/**
 * @group Controllers - Employee
 *
 * Manage Employee resources.
 */
class EmployeeController extends Controller
{
    public function __construct(protected HRService $service) {}

    /**
     * List employees with eager loading to prevent N+1 queries.
     *
     * @queryParam search string Search by name or email
     * @queryParam department integer Filter by department ID
     * @queryParam status string Filter by employment status
     * @queryParam per_page integer Results per page (default 15, max 100)
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Employee::class);

        $search = $request->query('search');
        $department = $request->query('department_id') ?? $request->query('department');
        $status = $request->query('status');
        $perPage = min((int) ($request->query('per_page', 15)), 100);

        // Eager load all frequently-accessed relationships
        $query = Employee::with('department', 'jobPosition', 'manager', 'user')
            // Chantier 32.17 (HR deep 14-layer audit): this had zero
            // tenant/company scoping at all — any authenticated user with
            // hr.employee.view-any (which includes the broad 'employee'
            // role) could list every company's employees, confirmed
            // empirically. when()-guarded: a no-op when the caller has no
            // real company_id (pre-chantier data, not-yet-provisioned
            // user), matching the established pattern used throughout this
            // app for this ongoing company_id rollout.
            ->when($request->user()?->company_id, fn ($q, $companyId) => $q->where('company_id', $companyId));

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%$search%")
                    ->orWhere('last_name', 'like', "%$search%")
                    ->orWhere('email', 'like', "%$search%");
            });
        }

        if ($department) {
            $query->where('department_id', $department);
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($request->query('with_hierarchy')) {
            $query->orderByRaw('manager_id IS NULL DESC')->orderBy('id');
        } else {
            $query->latest('created_at');
        }
        $employees = $query->paginate($perPage);

        return EmployeeResource::collection($employees);
    }

    public function store(StoreEmployeeRequest $request)
    {
        $this->authorize('create', Employee::class);

        // Chantier 32.17: company_id was never populated anywhere on
        // Employee — set here (server-side, never client-supplied, since
        // StoreEmployeeRequest's own rules() never allow it) so it flows
        // through to index()'s scoping and to Employee{,Department,
        // JobPosition}Policy's sameCompany() checks above.
        $employee = $this->service->createEmployee(array_merge(
            $request->validated(),
            ['company_id' => $request->user()->company_id],
        ));

        return (new EmployeeResource($employee))->response()->setStatusCode(201);
    }

    /**
     * Show a single employee with all related data.
     */
    public function show(Employee $employee)
    {
        $this->authorize('view', $employee);

        $employee->load('department', 'jobPosition', 'manager', 'user');

        return new EmployeeResource($employee);
    }

    public function export(Request $request)
    {
        // Reuses the 'viewAny' permission (hr.employee.view-any) rather than the
        // policy's 'export' method: hr.employee.export was never seeded as a real
        // permission (RolesAndPermissionsSeeder only generates view-any/view/create/
        // update/delete per resource), so gating on it would 403 every non-super-admin
        // user including hr-manager/admin — a functional regression, not a fix.
        $this->authorize('viewAny', Employee::class);

        $employees = Employee::with('department', 'jobPosition', 'manager')->get();
        $csv = "Name,Title,Department,Manager,Reports\n";
        foreach ($employees as $emp) {
            $name = trim(($emp->first_name ?? '') . ' ' . ($emp->last_name ?? ''));
            $csv .= implode(',', [
                '"' . str_replace('"', '""', $name) . '"',
                '"' . str_replace('"', '""', $emp->jobPosition?->title ?? '') . '"',
                '"' . str_replace('"', '""', $emp->department?->name ?? '') . '"',
                '"' . str_replace('"', '""', trim(($emp->manager?->first_name ?? '') . ' ' . ($emp->manager?->last_name ?? ''))) . '"',
                $emp->subordinates()->count(),
            ]) . "\n";
        }

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="employees.csv"',
        ]);
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee)
    {
        $this->authorize('update', $employee);

        $data = $request->validated();

        // Prevent circular management hierarchy
        if (isset($data['manager_id']) && $data['manager_id']) {
            $managerId = (int) $data['manager_id'];
            $currentId = $employee->id;
            // Walk up the chain; if we reach $currentId → circular
            $visited = [];
            $cursor = $managerId;
            while ($cursor) {
                if ($cursor === $currentId) {
                    return response()->json(['message' => 'Circular management hierarchy detected.'], 422);
                }
                if (in_array($cursor, $visited, true)) {
                    break; // break cycles in existing data
                }
                $visited[] = $cursor;
                $mgr = Employee::find($cursor);
                $cursor = $mgr ? (int) ($mgr->manager_id ?? 0) : 0;
            }
        }

        $updated = $this->service->updateEmployee($employee, $data);

        return new EmployeeResource($updated);
    }

    public function destroy(Request $request, Employee $employee)
    {
        $this->authorize('delete', $employee);

        // Chantier 32.17 (HR deep 14-layer audit): this had zero business
        // rule at all — a real, if never-wired-up, guard for exactly this
        // ("Cannot delete an active employee without force") already
        // existed on the confirmed-dead, zero-caller Modules\HR\Services\
        // EmployeeService (deleted in the same chantier, redundant with
        // this controller/HRService in every other respect) — folded the
        // one genuinely useful rule it demonstrated into the real,
        // routed delete path instead of losing it.
        if ($employee->status === 'active' && ! $request->boolean('force')) {
            return response()->json([
                'message' => 'Cannot delete an active employee without force. Pass ?force=1 to override.',
            ], 409);
        }

        $employee->delete();

        return response()->noContent();
    }

    public function byDepartment(int $departmentId)
    {
        $this->authorize('viewAny', Employee::class);

        $employees = $this->service->getEmployeesByDepartment($departmentId);

        return EmployeeResource::collection($employees);
    }

    public function metrics()
    {
        $this->authorize('viewAny', Employee::class);

        return response()->json($this->service->getHRMetrics());
    }
}
