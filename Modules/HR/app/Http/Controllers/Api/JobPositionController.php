<?php

declare(strict_types=1);

namespace Modules\HR\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\HR\Http\Resources\JobPositionResource;
use Modules\HR\Models\JobPosition;

/**
 * @group HR - JobPosition
 *
 * Manage job positions and org chart.
 */
class JobPositionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = JobPosition::withCount('employees')
            ->with('department')
            ->when(
                $request->has('is_active'),
                fn ($q) => $q->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN))
            )
            ->when($request->department_id, fn ($q, $v) => $q->where('department_id', $v))
            ->when($request->search, fn ($q, $s) => $q->where('title', 'like', "%{$s}%"));

        return response()->json(
            JobPositionResource::collection($query->orderBy('title')->paginate(min((int) ($request->per_page ?? 25), 100)))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'department_id' => ['nullable', 'exists:hr_departments,id'],
            'title' => ['required', 'string', 'max:255'],
            'level' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'requirements' => ['nullable', 'array'],
            'is_active' => ['boolean'],
        ]);

        $position = JobPosition::create($validated);

        return response()->json(
            new JobPositionResource($position->load('department')),
            201
        );
    }

    public function show(JobPosition $jobPosition): JsonResponse
    {
        return response()->json(
            new JobPositionResource($jobPosition->loadCount('employees')->load('department'))
        );
    }

    public function update(Request $request, JobPosition $jobPosition): JsonResponse
    {
        $validated = $request->validate([
            'department_id' => ['nullable', 'exists:hr_departments,id'],
            'title' => ['sometimes', 'string', 'max:255'],
            'level' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'requirements' => ['nullable', 'array'],
            'is_active' => ['boolean'],
        ]);

        $jobPosition->update($validated);

        return response()->json(
            new JobPositionResource($jobPosition->fresh()->loadCount('employees')->load('department'))
        );
    }

    public function destroy(JobPosition $jobPosition): JsonResponse
    {
        $jobPosition->delete();

        return response()->json(null, 204);
    }
}
