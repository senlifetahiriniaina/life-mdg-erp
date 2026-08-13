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
        $projects = TimeTrackingProject::query()
            ->with(['department'])
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
            end_date: $request->end_date
        );

        return (new TrackingProjectResource($project))->response()->setStatusCode(201);
    }

    public function show(TimeTrackingProject $project): \Illuminate\Http\JsonResponse|\Illuminate\Http\Resources\Json\JsonResource
    {
        $project->load(['department']);

        return new TrackingProjectResource($project);
    }

    public function update(UpdateTrackingProjectRequest $request, TimeTrackingProject $project): \Illuminate\Http\JsonResponse|\Illuminate\Http\Resources\Json\JsonResource
    {
        $project->update($request->validated());

        return new TrackingProjectResource($project);
    }

    public function destroy(TimeTrackingProject $project): JsonResponse
    {
        $project->forceDelete();

        return response()->json(['message' => 'Tracking project deleted'], 200);
    }

    public function timesheets(Request $request, TimeTrackingProject $project): \Illuminate\Http\JsonResponse|\Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        $entries = $this->service->getProjectTimesheets(
            project_id: $project->id,
            from_date: $request->from_date,
            to_date: $request->to_date
        );

        return TimesheetEntryResource::collection($entries);
    }

    public function metrics(TimeTrackingProject $project): JsonResponse
    {
        $metrics = $this->service->getProjectMetrics($project->id);

        return response()->json($metrics);
    }
}
