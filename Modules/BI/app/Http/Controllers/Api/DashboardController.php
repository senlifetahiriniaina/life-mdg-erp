<?php

declare(strict_types=1);

namespace Modules\BI\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\BI\Http\Requests\StoreDashboardRequest;
use Modules\BI\Http\Requests\UpdateDashboardRequest;
use Modules\BI\Http\Resources\DashboardResource;
use Modules\BI\Models\Dashboard;

/**
 * @group BI - Dashboard
 *
 * Retrieve and manage BI dashboards.
 */
class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse|JsonResource
    {
        $dashboards = Dashboard::with('user')->withCount('widgets')
            ->where(function ($query) use ($request) {
                $query->where('user_id', $request->user()->id)
                    ->orWhere('is_public', true);
            })
            ->latest()
            ->paginate(25);

        return DashboardResource::collection($dashboards);
    }

    public function store(StoreDashboardRequest $request): JsonResponse|JsonResource
    {
        $dashboard = Dashboard::create(array_merge($request->validated(), ['user_id' => $request->user()->id]));

        return (new DashboardResource($dashboard))->response()->setStatusCode(201);
    }

    public function show(Dashboard $dashboard): JsonResponse|JsonResource
    {
        $this->authorize('view', $dashboard);

        return new DashboardResource($dashboard->load('widgets'));
    }

    public function update(UpdateDashboardRequest $request, Dashboard $dashboard): JsonResponse|JsonResource
    {
        $this->authorize('update', $dashboard);
        $dashboard->update($request->validated());

        return new DashboardResource($dashboard->fresh());
    }

    public function destroy(Dashboard $dashboard): JsonResponse
    {
        $this->authorize('delete', $dashboard);
        $dashboard->delete();

        return response()->json(null, 204);
    }
}
