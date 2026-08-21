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
            // Chantier 32.15: subject_type previously accepted ANY string — a real
            // arbitrary-class information-disclosure vector (see CRMServiceProvider's
            // registerActivitySubjectMorphMap() docblock). Restricted to the real morph-map
            // allowlist, never a raw class name from client input.
            'subject_type' => ['nullable', 'string', 'in:contact,account,lead,opportunity'],
            'subject_id' => ['nullable', 'integer', 'required_with:subject_type'],
        ]);

        $this->assertSubjectSameCompany($request, $validated['subject_type'] ?? null, $validated['subject_id'] ?? null);

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
            'subject_type' => ['nullable', 'string', 'in:contact,account,lead,opportunity'],
            'subject_id' => ['nullable', 'integer', 'required_with:subject_type'],
        ]);

        if (array_key_exists('subject_type', $validated)) {
            $this->assertSubjectSameCompany($request, $validated['subject_type'] ?? null, $validated['subject_id'] ?? null);
        }

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

    /**
     * Chantier 32.15: closes the second half of the subject_type IDOR — even restricted to
     * the real CRM-domain allowlist (contact/account/lead/opportunity), subject_id was never
     * checked against the caller's own company, so an activity could still be linked to (and
     * later disclose, via load('subject')) another company's own contact/account/lead/
     * opportunity by id. 404s rather than 403s to avoid confirming a cross-tenant id exists.
     */
    private function assertSubjectSameCompany(Request $request, ?string $subjectType, ?int $subjectId): void
    {
        if ($subjectType === null || $subjectId === null) {
            return;
        }

        $companyId = $request->user()->company_id;

        $exists = match ($subjectType) {
            'contact' => \Modules\CRM\Models\Contact::where('id', $subjectId)->where('company_id', $companyId)->exists(),
            'account' => \Modules\CRM\Models\Account::where('id', $subjectId)->where('company_id', $companyId)->exists(),
            'lead' => \Modules\CRM\Models\Lead::where('id', $subjectId)->where('company_id', $companyId)->exists(),
            'opportunity' => \Modules\CRM\Models\Opportunity::where('id', $subjectId)->where('tenant_id', $companyId)->exists(),
            default => false,
        };

        abort_unless($exists, 404, 'Subject record not found.');
    }
}
