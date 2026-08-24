<?php

namespace Modules\Timesheets\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Timesheets\Http\Requests\StoreTrackingProjectRequest;
use Modules\Timesheets\Http\Requests\UpdateTrackingProjectRequest;
use Modules\Timesheets\Http\Resources\TimesheetEntryResource;
use Modules\Timesheets\Http\Resources\TrackingProjectResource;
use Modules\Timesheets\Models\TimeTrackingProject;
use Modules\Timesheets\Services\TimesheetService;

/**
 * @group Controllers - Tracking Project
 *
 * Manage Tracking Project resources.
 */
class TrackingProjectController extends Controller
{
    public function __construct(
        private TimesheetService $service
    ) {}

    public function index(Request $request): \Illuminate\Http\JsonResponse|\Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        // Chantier 32.19: same when()-guarded company scoping already
        // established throughout this app (e.g. Projects::index()) —
        // tenant_id is only filtered when the caller actually has a
        // company_id, so pre-chantier/not-yet-provisioned callers still
        // see the unscoped set rather than an empty list.
        $projects = TimeTrackingProject::query()
            ->with(['department'])
            ->when($request->user()?->company_id, fn ($q, $companyId) => $q->where('tenant_id', $companyId))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->department_id, fn ($q) => $q->where('department_id', $request->department_id))
            ->paginate($request->per_page ?? 50);

        return TrackingProjectResource::collection($projects);
    }

    public function store(StoreTrackingProjectRequest $request): \Illuminate\Http\JsonResponse|\Illuminate\Http\Resources\Json\JsonResource
    {
        $project = $this->service->createTrackingProject(
            name: $request->name,
            code: $request->code,
            description: $request->description,
            budget_hours: $request->budget_hours,
            department_id: $request->department_id,
            start_date: $request->start_date,
            end_date: $request->end_date,
            tenant_id: $request->user()?->company_id,
        );

        return (new TrackingProjectResource($project))->response()->setStatusCode(201);
    }

    public function show(Request $request, TimeTrackingProject $project): \Illuminate\Http\JsonResponse|\Illuminate\Http\Resources\Json\JsonResource
    {
        $this->assertSameCompany($request, $project);

        $project->load(['department']);

        return new TrackingProjectResource($project);
    }

    public function update(UpdateTrackingProjectRequest $request, TimeTrackingProject $project): \Illuminate\Http\JsonResponse|\Illuminate\Http\Resources\Json\JsonResource
    {
        $this->assertSameCompany($request, $project);

        $project->update($request->validated());

        return new TrackingProjectResource($project);
    }

    public function destroy(Request $request, TimeTrackingProject $project): JsonResponse
    {
        $this->assertSameCompany($request, $project);

        $project->forceDelete();

        return response()->json(['message' => 'Tracking project deleted'], 200);
    }

    public function timesheets(Request $request, TimeTrackingProject $project): \Illuminate\Http\JsonResponse|\Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        $this->assertSameCompany($request, $project);

        $entries = $this->service->getProjectTimesheets(
            project_id: $project->id,
            from_date: $request->from_date,
            to_date: $request->to_date
        );

        return TimesheetEntryResource::collection($entries);
    }

    public function metrics(Request $request, TimeTrackingProject $project): JsonResponse
    {
        $this->assertSameCompany($request, $project);

        $metrics = $this->service->getProjectMetrics($project->id);

        return response()->json($metrics);
    }

    /**
     * Chantier 32.19: 404 (not 403, to avoid confirming another company's
     * id even exists) — a no-op when either side carries no real tenant_id
     * yet, matching this app's established graceful-degradation pattern
     * for the ongoing company_id rollout (Modules\Projects\...\ScopesToProjectCompany
     * is the precedent this mirrors).
     */
    private function assertSameCompany(Request $request, TimeTrackingProject $project): void
    {
        $callerCompanyId = $request->user()?->company_id;

        if ($callerCompanyId !== null && $project->tenant_id !== null
            && (int) $project->tenant_id !== (int) $callerCompanyId) {
            abort(404);
        }
    }
}
