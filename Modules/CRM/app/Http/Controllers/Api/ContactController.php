<?php

declare(strict_types=1);

namespace Modules\CRM\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Modules\CRM\Models\Contact;

/**
 * @group CRM - Contacts
 *
 * Manage CRM contacts: people associated with accounts, leads and opportunities.
 */
class ContactController extends Controller
{
    private const CACHE_TTL = 120;

    private const CACHE_VERSION_KEY = 'crm.contacts.v';

    /**
     * List contacts.
     *
     * @queryParam search string Filter by name or email. Example: John
     * @queryParam status string Filter by status (active, inactive, prospect). Example: active
     * @queryParam owner_id integer Filter by owner user ID. Example: 1
     * @queryParam per_page integer Results per page (max 100). Example: 25
     *
     * @response 200 scenario="Success" {"data": [{"id": 1, "first_name": "John", "last_name": "Doe", "email": "john.doe@example.com", "status": "active"}], "meta": {"current_page": 1, "total": 42}}
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) ($request->per_page ?? 25), 100);

        // Skip cache for search queries — too many variations, low reuse value
        if ($request->filled('search')) {
            return response()->json($this->buildQuery($request)->paginate($perPage));
        }

        $version = Cache::get(self::CACHE_VERSION_KEY, 1);
        // Chantier 19: the cache key never included company_id — filtering the query by
        // company was not enough on its own, since Company B's request could still be served
        // Company A's already-cached page from a shared cache key. Company id is now part of
        // the key so the cache itself is tenant-scoped, not just the underlying query.
        $cacheKey = sprintf(
            'crm.contacts.%d.c%s.p%d.s%s.o%s.pp%d',
            $version,
            $request->user()->company_id ?? '0',
            $request->get('page', 1),
            $request->get('status', ''),
            $request->get('owner_id', ''),
            $perPage,
        );

        $data = Cache::remember($cacheKey, self::CACHE_TTL, fn () => $this->buildQuery($request)->paginate($perPage));

        return response()->json($data);
    }

    /**
     * Create contact
     *
     * Creates a new CRM contact and assigns it to the authenticated user.
     *
     * @bodyParam first_name string required First name. Example: John
     * @bodyParam last_name string required Last name. Example: Doe
     * @bodyParam email string Email address. Example: john.doe@example.com
     * @bodyParam phone string Phone number. Example: +1234567890
     * @bodyParam mobile string Mobile number. Example: +1987654321
     * @bodyParam job_title string Job title. Example: Sales Manager
     * @bodyParam department string Department name. Example: Sales
     * @bodyParam linkedin_url string LinkedIn profile URL. Example: https://linkedin.com/in/johndoe
     * @bodyParam source string Lead source. Example: website
     * @bodyParam status string Status (active, inactive, prospect). Example: active
     * @bodyParam account_id integer ID of the associated CRM account. Example: 5
     * @bodyParam notes string Free-form notes. Example: Met at conference.
     * @bodyParam custom_fields object Key-value map of custom fields. Example: {"region":"EMEA"}
     *
     * @response 201 scenario="Created" {"id": 1, "first_name": "John", "last_name": "Doe", "email": "john.doe@example.com", "status": "active"}
     * @response 422 scenario="Validation error" {"message": "The first name field is required.", "errors": {"first_name": ["The first name field is required."]}}
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'unique:crm_contacts,email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'mobile' => ['nullable', 'string', 'max:50'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            'linkedin_url' => ['nullable', 'url'],
            'source' => ['nullable', 'string'],
            'status' => ['nullable', 'in:active,inactive,prospect'],
            'account_id' => ['nullable', 'exists:crm_accounts,id'],
            'notes' => ['nullable', 'string'],
            'custom_fields' => ['nullable', 'array'],
        ]);

        $contact = Contact::create(array_merge($validated, [
            'owner_id' => $request->user()->id,
            // Chantier 19: never populated before — every real contact was created with no
            // company_id, which is what let ContactController::index()'s new company scoping
            // (and ContactPolicy's new sameCompany() check) be trivially bypassed by anyone.
            'company_id' => $request->user()->company_id,
        ]));
        $this->bustCache();

        return response()->json($contact->load('account', 'owner'), 201);
    }

    /**
     * Get contact
     *
     * Returns a single CRM contact with related account, owner, leads, opportunities and activities.
     *
     * @urlParam contact int required The contact ID. Example: 1
     *
     * @response 200 scenario="Success" {"id": 1, "first_name": "John", "last_name": "Doe", "email": "john.doe@example.com", "account": {}, "owner": {}}
     * @response 403 scenario="Unauthorized" {"message": "This action is unauthorized."}
     * @response 404 scenario="Not found" {"message": "Not found."}
     */
    public function show(Contact $contact): JsonResponse
    {
        $this->authorize('view', $contact);

        return response()->json($contact->load('account', 'owner', 'leads', 'opportunities', 'activities'));
    }

    /**
     * Update contact
     *
     * Updates an existing CRM contact. All fields are optional (PATCH semantics).
     * Requires ownership or admin/manager role.
     *
     * @urlParam contact int required The contact ID. Example: 1
     *
     * @bodyParam first_name string First name. Example: John
     * @bodyParam last_name string Last name. Example: Doe
     * @bodyParam email string Email address. Example: john.doe@example.com
     * @bodyParam phone string Phone number. Example: +1234567890
     * @bodyParam mobile string Mobile number. Example: +1987654321
     * @bodyParam job_title string Job title. Example: Sales Manager
     * @bodyParam department string Department name. Example: Sales
     * @bodyParam linkedin_url string LinkedIn profile URL. Example: https://linkedin.com/in/johndoe
     * @bodyParam source string Lead source. Example: website
     * @bodyParam status string Status (active, inactive, prospect). Example: active
     * @bodyParam account_id integer ID of the associated CRM account. Example: 5
     * @bodyParam notes string Free-form notes. Example: Met at conference.
     * @bodyParam custom_fields object Key-value map of custom fields. Example: {"region":"EMEA"}
     *
     * @response 200 scenario="Updated" {"id": 1, "first_name": "John", "last_name": "Doe", "status": "active"}
     * @response 403 scenario="Unauthorized" {"message": "This action is unauthorized."}
     * @response 422 scenario="Validation error" {"message": "The email must be a valid email address.", "errors": {"email": ["The email must be a valid email address."]}}
     */
    public function update(Request $request, Contact $contact): JsonResponse
    {
        $this->authorize('update', $contact);
        $validated = $request->validate([
            'first_name' => ['sometimes', 'string', 'max:255'],
            'last_name' => ['sometimes', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'mobile' => ['nullable', 'string', 'max:50'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            'linkedin_url' => ['nullable', 'url'],
            'source' => ['nullable', 'string'],
            'status' => ['nullable', 'in:active,inactive,prospect'],
            'account_id' => ['nullable', 'exists:crm_accounts,id'],
            'notes' => ['nullable', 'string'],
            'custom_fields' => ['nullable', 'array'],
        ]);

        $contact->update($validated);
        $this->bustCache();

        return response()->json($contact->fresh('account', 'owner'));
    }

    /**
     * Delete contact
     *
     * Permanently deletes a CRM contact. Requires ownership or admin/manager role.
     *
     * @urlParam contact int required The contact ID. Example: 1
     *
     * @response 204 scenario="Deleted"
     * @response 403 scenario="Unauthorized" {"message": "This action is unauthorized."}
     * @response 404 scenario="Not found" {"message": "Not found."}
     */
    public function destroy(Contact $contact): JsonResponse
    {
        $this->authorize('delete', $contact);
        $contact->delete();
        $this->bustCache();

        return response()->json(null, 204);
    }

    /** @return Builder<Contact> */
    private function buildQuery(Request $request): Builder
    {
        // Chantier 19: index() had zero tenant scoping of any kind — any authenticated user
        // of any company could list every other company's contacts. Scoped to the caller's
        // own company_id, matching the ?? 0 sentinel convention already used elsewhere in
        // this session (a contact/user with no real company_id are treated as the same
        // "untagged" bucket rather than leaving NULL-vs-NULL ambiguous).
        return Contact::with('account', 'owner')
            ->where('company_id', $request->user()->company_id)
            ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('first_name', 'like', "%{$s}%")
                    ->orWhere('last_name', 'like', "%{$s}%")
                    ->orWhere('email', 'like', "%{$s}%");
            }))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->owner_id, fn ($q, $v) => $q->where('owner_id', $v))
            ->latest();
    }

    private function bustCache(): void
    {
        Cache::increment(self::CACHE_VERSION_KEY);
    }
}
