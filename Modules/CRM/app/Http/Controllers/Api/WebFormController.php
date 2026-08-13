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
class WebFormController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(WebForm::withCount('submissions')->latest()->paginate(25));
    }

    public function store(Request $request): JsonResponse
    {
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

        $form = WebForm::create([...$data, 'created_by' => $request->user()->id]);

        return response()->json($form, 201);
    }

    public function show(WebForm $form): JsonResponse
    {
        return response()->json($form->load('submissions'));
    }

    public function update(Request $request, WebForm $form): JsonResponse
    {
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
        $form->delete();

        return response()->json(null, 204);
    }

    /** Public submission endpoint — no authentication required */
    public function submit(Request $request, string $slug): JsonResponse
    {
        $form = WebForm::where('slug', $slug)->where('is_active', true)->firstOrFail();

        // Validate required fields from form definition
        $rules = [];
        foreach ($form->fields as $field) {
            if (! empty($field['required'])) {
                $rules[$field['name']] = 'required';
            }
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
                'phone' => $data['phone'] ?? null,
                'company' => $data['company'] ?? null,
                'source' => $form->default_lead_source,
                'status' => 'new',
                'description' => "Source: {$form->name}",
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
