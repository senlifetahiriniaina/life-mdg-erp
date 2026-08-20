<?php

declare(strict_types=1);

namespace Modules\Workflow\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
// Chantier 19 Lot 3: was `use Modules\Workflow\Models\AutomationFlowTemplate;`
// — same fatal class-not-found bug as WorkflowScheduleController (see its
// own docblock), the real model is one namespace level deeper. Dormant only
// because this controller also has zero routes registered anywhere.
use Modules\Workflow\Models\Automation\AutomationFlowTemplate;

/**
 * @group Workflow - Templates
 *
 * Manage and apply workflow templates.
 */
class WorkflowTemplateController extends Controller
{
    /**
     * List all available workflow templates.
     *
     * @queryParam category string Filter by category. Example: crm
     * @queryParam module string Filter by module. Example: CRM
     */
    public function index(Request $request): JsonResponse
    {
        $query = AutomationFlowTemplate::query()
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->category))
            ->when($request->filled('module'), fn ($q) => $q->where('module', $request->module))
            ->latest();

        return response()->json(['data' => $query->get()]);
    }

    /**
     * Get a single template.
     */
    public function show(AutomationFlowTemplate $template): JsonResponse
    {
        return response()->json(['data' => $template]);
    }

    /**
     * Create a new template.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'category'    => 'nullable|string|max:100',
            'module'      => 'nullable|string|max:100',
            'nodes'       => 'required|array',
            'connections' => 'sometimes|array',
            'config'      => 'sometimes|array',
        ]);

        $template = AutomationFlowTemplate::create($validated);

        return response()->json(['data' => $template, 'message' => 'Template created'], 201);
    }

    /**
     * Update a template.
     */
    public function update(Request $request, AutomationFlowTemplate $template): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'category'    => 'nullable|string|max:100',
            'module'      => 'nullable|string|max:100',
            'nodes'       => 'sometimes|array',
            'connections' => 'sometimes|array',
            'config'      => 'sometimes|array',
        ]);

        $template->update($validated);

        return response()->json(['data' => $template, 'message' => 'Template updated']);
    }

    /**
     * Delete a template.
     */
    public function destroy(AutomationFlowTemplate $template): JsonResponse
    {
        $template->delete();

        return response()->json(['message' => 'Template deleted']);
    }

    /**
     * Apply a template to create a new automation flow.
     */
    public function apply(Request $request, AutomationFlowTemplate $template): JsonResponse
    {
        // Chantier 19 Lot 3: was `$request->user()?->tenant_id ?? 1` — the
        // phantom tenant_id column, matching every other tenantId() fix in
        // this module.
        $tenantId = (int) ($request->user()?->company_id ?? 0);
        $userId   = $request->user()?->id;

        $flow = $template->instantiateForTenant($tenantId, $userId);

        return response()->json(['data' => $flow, 'message' => 'Template applied successfully'], 201);
    }

    /**
     * Preview what a template would generate (dry-run, no write).
     */
    public function preview(AutomationFlowTemplate $template): JsonResponse
    {
        return response()->json([
            'data' => [
                'template'       => $template,
                'estimated_nodes' => count($template->nodes ?? []),
                'estimated_connections' => count($template->connections ?? []),
            ],
        ]);
    }
}
