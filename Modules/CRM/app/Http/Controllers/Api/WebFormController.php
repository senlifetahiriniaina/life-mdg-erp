<?php

declare(strict_types=1);

namespace Modules\CRM\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\CRM\Models\Lead;
use Modules\CRM\Models\WebForm;
use Modules\CRM\Models\WebFormSubmission;

/**
 * @group CRM - Web Forms
 */
/**
 * Chantier 32.15 (CRM 14-layer audit): this whole controller had zero authorize()/tenant-
 * scoping calls anywhere — any authenticated CRM-module user of any company could list/read/
 * update/delete every other company's public lead-capture forms, confirmed empirically before
 * this fix. crm_web_forms already carried a real `tenant_id` column, just never populated by
 * store() nor filtered on anywhere. submit() (the public, unauthenticated endpoint) is fixed
 * separately below so the resulting Lead is correctly tenant-tagged from the form's own
 * tenant_id.
 */
class WebFormController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', WebForm::class);

        return response()->json(
            WebForm::withCount('submissions')
                ->where('tenant_id', $request->user()->company_id)
                ->latest()
                ->paginate(25)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', WebForm::class);

        $data = $request->validate([
            'name' => 'required|string|max:200',
            'slug' => 'required|string|unique:crm_web_forms,slug',
            'fields' => 'required|array|min:1',
            'fields.*.name' => 'required|string',
            'fields.*.label' => 'required|string',
            'fields.*.type' => 'required|in:text,email,phone,textarea,select,checkbox',
            'fields.*.required' => 'boolean',
            'redirect_url' => 'nullable|url',
            'success_message' => 'nullable|string',
            'create_lead' => 'boolean',
            'pipeline_id' => 'nullable|integer',
            'default_lead_source' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $form = WebForm::create([
            ...$data,
            'created_by' => $request->user()->id,
            'tenant_id' => $request->user()->company_id,
        ]);

        return response()->json($form, 201);
    }

    public function show(WebForm $form): JsonResponse
    {
        $this->authorize('view', $form);

        return response()->json($form->load('submissions'));
    }

    public function update(Request $request, WebForm $form): JsonResponse
    {
        $this->authorize('update', $form);

        $data = $request->validate([
            'name' => 'sometimes|string|max:200',
            'fields' => 'sometimes|array',
            'is_active' => 'boolean',
        ]);
        $form->update($data);

        return response()->json($form);
    }

    public function destroy(WebForm $form): JsonResponse
    {
        $this->authorize('delete', $form);

        $form->delete();

        return response()->json(null, 204);
    }

    /** Public submission endpoint — no authentication required */
    public function submit(Request $request, string $slug): JsonResponse
    {
        $form = WebForm::where('slug', $slug)->where('is_active', true)->firstOrFail();

        // Chantier 32.15: this only ever built a validation rule for `required` fields —
        // Laravel's validate() only returns keys that have SOME rule attached, so any
        // optional field the form itself defines (e.g. phone, company) was silently dropped
        // from $data on every real submission regardless of what the client actually sent,
        // confirmed empirically before this fix. Every field the form defines now gets at
        // least a `nullable` rule so it's genuinely captured.
        $rules = [];
        foreach ($form->fields as $field) {
            $rules[$field['name']] = empty($field['required']) ? 'nullable' : 'required';
        }

        $data = $request->validate($rules);

        $submission = WebFormSubmission::create([
            'form_id' => $form->id,
            'data' => $data,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        if ($form->create_lead) {
            $lead = Lead::create([
                'title' => $data['name'] ?? 'Web Form Lead',
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'company' => $data['company'] ?? null,
                'source' => $form->default_lead_source,
                'status' => 'new',
                'description' => "Source: {$form->name}",
                // Chantier 32.15: this was never set — a lead created from a real public web
                // form submission had no tenant boundary at all, meaning LeadController::
                // index()'s (already-correct) company_id filter could never surface it to
                // anyone; the lead pipeline this form exists to feed was silently invisible.
                // The form's own tenant_id (set on create, above) is the only tenant context
                // available here, since submit() is deliberately unauthenticated.
                'company_id' => $form->tenant_id,
            ]);

            $submission->update(['lead_id' => $lead->id]);
        }

        return response()->json([
            'success' => true,
            'message' => $form->success_message ?? 'Thank you for your submission!',
            'redirect_url' => $form->redirect_url,
        ]);
    }
}
