<?php

declare(strict_types=1);

namespace Modules\CRM\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\CRM\Models\Activity;

/**
 * @group CRM - Activity
 *
 * Log and track CRM activities (calls, meetings, emails).
 */
class ActivityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Activity::with('user')
            ->when($request->type, fn ($q, $v) => $q->where('type', $v))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->user_id, fn ($q, $v) => $q->where('user_id', $v))
            ->when($request->subject_type, fn ($q, $v) => $q->where('subject_type', $v))
            ->when($request->subject_id, fn ($q, $v) => $q->where('subject_id', $v));

        return response()->json($query->latest()->paginate(min((int) ($request->per_page ?? 25), 100)));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'in:call,email,meeting,task,note'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'in:planned,done,cancelled'],
            'due_at' => ['nullable', 'date'],
            'user_id' => ['nullable', 'exists:users,id'],
            'subject_type' => ['nullable', 'string', 'max:255'],
            'subject_id' => ['nullable', 'integer'],
        ]);

        $activity = Activity::create(array_merge($validated, [
            'user_id' => $validated['user_id'] ?? $request->user()->id,
        ]));

        return response()->json($activity->load('user'), 201);
    }

    public function show(Activity $activity): JsonResponse
    {
        return response()->json($activity->load('user', 'subject'));
    }

    public function update(Request $request, Activity $activity): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['sometimes', 'string', 'in:call,email,meeting,task,note'],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'in:planned,done,cancelled'],
            'due_at' => ['nullable', 'date'],
            'done_at' => ['nullable', 'date'],
            'user_id' => ['nullable', 'exists:users,id'],
            'subject_type' => ['nullable', 'string', 'max:255'],
            'subject_id' => ['nullable', 'integer'],
        ]);

        if (isset($validated['status']) && $validated['status'] === 'done' && $activity->done_at === null) {
            $validated['done_at'] = now();
        }

        $activity->update($validated);

        return response()->json($activity->fresh('user'));
    }

    public function destroy(Activity $activity): JsonResponse
    {
        $activity->delete();

        return response()->json(null, 204);
    }
}
