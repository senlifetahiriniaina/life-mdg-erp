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
/**
 * Chantier "CRM tenant-isolation follow-up": crm_activities had no company/tenant column at
 * all and this controller had zero `authorize()` calls anywhere — any authenticated CRM-module
 * user could list/view/update/delete any other company's logged calls/emails/meetings/notes,
 * confirmed via read before this fix. Rewired onto a new, additive `company_id` column plus a
 * new ActivityPolicy, matching the ContactController/ContactPolicy pattern.
 */
class ActivityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Activity::class);

        $query = Activity::with('user')
            ->where('company_id', $request->user()->company_id)
            ->when($request->type, fn ($q, $v) => $q->where('type', $v))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->user_id, fn ($q, $v) => $q->where('user_id', $v))
            ->when($request->subject_type, fn ($q, $v) => $q->where('subject_type', $v))
            ->when($request->subject_id, fn ($q, $v) => $q->where('subject_id', $v));

        return response()->json($query->latest()->paginate(min((int) ($request->per_page ?? 25), 100)));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Activity::class);

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
            'company_id' => $request->user()->company_id,
        ]));

        return response()->json($activity->load('user'), 201);
    }

    public function show(Activity $activity): JsonResponse
    {
        $this->authorize('view', $activity);

        return response()->json($activity->load('user', 'subject'));
    }

    public function update(Request $request, Activity $activity): JsonResponse
    {
        $this->authorize('update', $activity);

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
        $this->authorize('delete', $activity);

        $activity->delete();

        return response()->json(null, 204);
    }
}
