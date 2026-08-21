<?php

namespace Modules\HR\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\HR\Http\Requests\StoreDepartmentRequest;
use Modules\HR\Http\Requests\UpdateDepartmentRequest;
use Modules\HR\Http\Resources\DepartmentResource;
use Modules\HR\Models\Department;
use Modules\HR\Services\HRService;

/**
 * @group Controllers - Department
 *
 * Manage Department resources.
 */
class DepartmentController extends Controller
{
    public function __construct(protected HRService $service) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Department::class);

        // Chantier 32.17 (HR deep 14-layer audit): zero tenant/company
        // scoping at all before this — same cross-tenant leak already
        // documented (and fixed) on EmployeeController::index().
        $perPage = $request->query('per_page', 15);
        $departments = $this->service->getAllDepartments($perPage, $request->user()?->company_id);

        return DepartmentResource::collection($departments);
    }

    public function store(StoreDepartmentRequest $request)
    {
        $this->authorize('create', Department::class);

        $department = $this->service->createDepartment(array_merge(
            $request->validated(),
            ['company_id' => $request->user()->company_id],
        ));

        return (new DepartmentResource($department))->response()->setStatusCode(201);
    }

    public function show(Department $department)
    {
        $this->authorize('view', $department);

        $department->loadCount('employees')->load('manager:id,first_name,last_name');

        return new DepartmentResource($department);
    }

    public function update(UpdateDepartmentRequest $request, Department $department)
    {
        $this->authorize('update', $department);

        $updated = $this->service->updateDepartment($department, $request->validated());

        return new DepartmentResource($updated);
    }

    public function destroy(Department $department)
    {
        $this->authorize('delete', $department);

        $department->delete();

        return response()->noContent();
    }

    public function metrics(Department $department)
    {
        $this->authorize('view', $department);

        return response()->json($this->service->getDepartmentMetrics($department));
    }
}
