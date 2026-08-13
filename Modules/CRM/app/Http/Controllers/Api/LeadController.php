<?php

declare(strict_types=1);

namespace Modules\CRM\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\CRM\Models\Lead;

/**
 * @group CRM - Lead
 *
 * Manage sales leads through the qualification pipeline.
 */
class LeadController extends Controller
{
    /**
     * List leads
     *
     * Returns a paginated list of CRM leads. Results are ordered newest first.
     *
     * @queryParam search string Search by lead title. Example: enterprise
     * @queryParam status string Filter by status (new, contacted, qualified, unqualified, converted). Example: new
     * @queryParam owner_id integer Filter by owner user ID. Example: 1
     * @queryParam per_page integer Results per page (max 100). Example: 25
     *
     * @response 200 scenario="Success" {"data": [{"id": 1, "title": "Enterprise Deal", "status": "new", "score": 75}], "meta": {"current_page": 1, "total": 10}}
     */
    public function index(Request $request): JsonResponse
    {
        $query = Lead::with('owner', 'contact')
            ->when($request->search, fn ($q, $s) => $q->where('title', 'like', "%{$s}%"))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->owner_id, fn ($q, $v) => $q->where('owner_id', $v));

        return response()->json($query->latest()->paginate(min((int) ($request->per_page ?? 25), 100)));
    }

    /**
     * Create lead
     *
     * Creates a new CRM lead. If `owner_id` is omitted the authenticated user is set as owner.
     *
     * @bodyParam title string required Lead title or name. Example: Enterprise Software Deal
     * @bodyParam owner_id integer ID of the owning user (defaults to authenticated user). Example: 2
     * @bodyParam contact_id integer ID of the associated CRM contact. Example: 5
     * @bodyParam status string Status (new, contacted, qualified, unqualified, converted). Example: new
     * @bodyParam source string Lead source channel. Example: cold_call
     * @bodyParam score integer Lead score 0–100. Example: 60
     * @bodyParam estimated_value number Estimated deal value. Example: 15000.00
     * @bodyParam currency string ISO 4217 currency code. Example: USD
     * @bodyParam description string Additional description. Example: Referred by partner.
     *
     * @response 201 scenario="Created" {"id": 1, "title": "Enterprise Software Deal", "status": "new", "score": 60, "owner": {}, "contact": null}
     * @response 422 scenario="Validation error" {"message": "The title field is required.", "errors": {"title": ["The title field is required."]}}
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'owner_id' => ['nullable', 'exists:users,id'],
            'contact_id' => ['nullable', 'exists:crm_contacts,id'],
            'status' => ['nullable', 'in:new,contacted,qualified,unqualified,converted'],
            'source' => ['nullable', 'string', 'max:100'],
            'score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'estimated_value' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'description' => ['nullable', 'string'],
        ]);

        $lead = Lead::create(array_merge($validated, [
            'owner_id' => $validated['owner_id'] ?? $request->user()->id,
        ]));

        return response()->json($lead->load('owner', 'contact'), 201);
    }

    /**
     * Get lead
     *
     * Returns a single CRM lead with its owner and associated contact.
     *
     * @urlParam lead int required The lead ID. Example: 1
     *
     * @response 200 scenario="Success" {"id": 1, "title": "Enterprise Software Deal", "status": "new", "owner": {}, "contact": {}}
     * @response 403 scenario="Unauthorized" {"message": "This action is unauthorized."}
     * @response 404 scenario="Not found" {"message": "Not found."}
     */
    public function show(Lead $lead): JsonResponse
    {
        $this->authorize('view', $lead);

        return response()->json($lead->load('owner', 'contact'));
    }

    /**
     * Update lead
     *
     * Updates an existing CRM lead. All fields are optional (PATCH semantics).
     * Requires ownership or admin/manager role.
     *
     * @urlParam lead int required The lead ID. Example: 1
     *
     * @bodyParam title string Lead title. Example: Enterprise Software Deal
     * @bodyParam owner_id integer ID of the owning user. Example: 2
     * @bodyParam contact_id integer ID of the associated CRM contact. Example: 5
     * @bodyParam status string Status (new, contacted, qualified, unqualified, converted). Example: qualified
     * @bodyParam source string Lead source channel. Example: website
     * @bodyParam score integer Lead score 0–100. Example: 85
     * @bodyParam estimated_value number Estimated deal value. Example: 20000.00
     * @bodyParam currency string ISO 4217 currency code. Example: USD
     * @bodyParam description string Additional description. Example: Upgraded to qualified.
     *
     * @response 200 scenario="Updated" {"id": 1, "title": "Enterprise Software Deal", "status": "qualified", "score": 85}
     * @response 403 scenario="Unauthorized" {"message": "This action is unauthorized."}
     * @response 422 scenario="Validation error" {"message": "The status must be one of: new, contacted, qualified, unqualified, converted.", "errors": {"status": ["The status must be one of: new, contacted, qualified, unqualified, converted."]}}
     */
    public function update(Request $request, Lead $lead): JsonResponse
    {
        $this->authorize('update', $lead);
        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'owner_id' => ['nullable', 'exists:users,id'],
            'contact_id' => ['nullable', 'exists:crm_contacts,id'],
            'status' => ['nullable', 'in:new,contacted,qualified,unqualified,converted'],
            'source' => ['nullable', 'string', 'max:100'],
            'score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'estimated_value' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'description' => ['nullable', 'string'],
        ]);

        $lead->update($validated);

        return response()->json($lead->fresh('owner', 'contact'));
    }

    /**
     * Delete lead
     *
     * Permanently deletes a CRM lead. Requires ownership or admin/manager role.
     *
     * @urlParam lead int required The lead ID. Example: 1
     *
     * @response 204 scenario="Deleted"
     * @response 403 scenario="Unauthorized" {"message": "This action is unauthorized."}
     * @response 404 scenario="Not found" {"message": "Not found."}
     */
    public function destroy(Lead $lead): JsonResponse
    {
        $this->authorize('delete', $lead);

        $lead->delete();

        return response()->json(null, 204);
    }
}
