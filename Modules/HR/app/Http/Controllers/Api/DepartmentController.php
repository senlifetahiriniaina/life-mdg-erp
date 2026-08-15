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
        $perPage = $request->query('per_page', 15);
        $departments = $this->service->getAllDepartments($perPage);

        return DepartmentResource::collection($departments);
    }

    public function store(StoreDepartmentRequest $request)
    {
        $department = $this->service->createDepartment($request->validated());

        return (new DepartmentResource($department))->response()->setStatusCode(201);
    }

    public function show(Department $department)
    {
        $department->loadCount('employees');

        return new DepartmentResource($department);
    }

    public function update(UpdateDepartmentRequest $request, Department $department)
    {
        $updated = $this->service->updateDepartment($department, $request->validated());

        return new DepartmentResource($updated);
    }

    public function destroy(Department $department)
    {
        $department->delete();

        return response()->noContent();
    }

    public function metrics(Department $department)
    {
        return response()->json($this->service->getDepartmentMetrics($department));
    }
}
