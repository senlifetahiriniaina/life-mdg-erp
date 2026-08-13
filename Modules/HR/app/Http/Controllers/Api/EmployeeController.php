<?php

namespace Modules\HR\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
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
        $search = $request->query('search');
        $department = $request->query('department_id') ?? $request->query('department');
        $status = $request->query('status');
        $perPage = min((int) ($request->query('per_page', 15)), 100);

        // Eager load all frequently-accessed relationships
        $query = Employee::with('department', 'jobPosition', 'manager', 'user');

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
        $employee = $this->service->createEmployee($request->validated());

        return (new EmployeeResource($employee))->response()->setStatusCode(201);
    }

    /**
     * Show a single employee with all related data.
     */
    public function show(Employee $employee)
    {
        $employee->load('department', 'jobPosition', 'manager', 'user');

        return new EmployeeResource($employee);
    }

    public function export(Request $request)
    {
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

    public function destroy(Employee $employee)
    {
        $employee->delete();

        return response()->noContent();
    }

    public function byDepartment(int $departmentId)
    {
        $employees = $this->service->getEmployeesByDepartment($departmentId);

        return EmployeeResource::collection($employees);
    }

    public function metrics()
    {
        return response()->json($this->service->getHRMetrics());
    }
}
