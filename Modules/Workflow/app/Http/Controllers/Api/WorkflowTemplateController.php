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
 *
 * Chantier 32.11: fixing the class-not-found import (Chantier 19 Lot 3)
 * only made this controller *constructible* — actually exercising it via a
 * real HTTP request (this chantier's empirical-verification methodology,
 * not code reading) surfaced a second, deeper bug underneath: every method
 * here was written against a schema this model has never had.
 * `AutomationFlowTemplate::$fillable` is `[name, description, category,
 * icon, flow_definition, is_builtin]` — `flow_definition` is a single
 * NOT NULL JSON column holding the *whole* template payload (nodes,
 * connections, trigger config, …), exactly the shape
 * `instantiateForTenant()` already reads from. This controller instead
 * validated/wrote `nodes`/`connections`/`config` as flat top-level
 * columns (which don't exist at all) and never touched `flow_definition`
 * — `store()` was a guaranteed NOT NULL constraint violation on every real
 * call, confirmed empirically. `index()`'s `module` filter and
 * `preview()`'s `$template->nodes`/`$template->connections` reads were the
 * same mismatch independently (no `module`/`nodes`/`connections` column or
 * accessor exists on this model at all — only `category` and
 * `flow_definition`). Fixed to build/read `flow_definition` for real.
 */
class WorkflowTemplateController extends Controller
{
    /**
     * List all available workflow templates.
     *
     * @queryParam category string Filter by category. Example: crm
     */
    public function index(Request $request): JsonResponse
    {
        $query = AutomationFlowTemplate::query()
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->category))
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
     *
     * @bodyParam name string required
     * @bodyParam description string
     * @bodyParam category string
     * @bodyParam icon string
     * @bodyParam nodes array required Node definitions (flow_definition.nodes).
     * @bodyParam connections array Connection definitions (flow_definition.connections).
     * @bodyParam trigger_type string
     * @bodyParam trigger_config array
     * @bodyParam variables array
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'            => 'required|string|max:255',
            'description'     => 'nullable|string',
            'category'        => 'nullable|string|max:100',
            'icon'            => 'nullable|string|max:10',
            'nodes'           => 'required|array',
            'connections'     => 'sometimes|array',
            'trigger_type'    => 'sometimes|string',
            'trigger_config'  => 'sometimes|array',
            'variables'       => 'sometimes|array',
        ]);

        $template = AutomationFlowTemplate::create([
            'name'            => $validated['name'],
            'description'     => $validated['description'] ?? null,
            'category'        => $validated['category'] ?? 'general',
            'icon'            => $validated['icon'] ?? '⚙️',
            'is_builtin'      => false,
            'flow_definition' => [
                'name'           => $validated['name'],
                'description'    => $validated['description'] ?? null,
                'icon'           => $validated['icon'] ?? '⚙️',
                'trigger_type'   => $validated['trigger_type'] ?? 'manual',
                'trigger_config' => $validated['trigger_config'] ?? [],
                'nodes'          => $validated['nodes'],
                'connections'    => $validated['connections'] ?? [],
                'variables'      => $validated['variables'] ?? [],
            ],
        ]);

        return response()->json(['data' => $template, 'message' => 'Template created'], 201);
    }

    /**
     * Update a template.
     */
    public function update(Request $request, AutomationFlowTemplate $template): JsonResponse
    {
        $validated = $request->validate([
            'name'            => 'sometimes|string|max:255',
            'description'     => 'nullable|string',
            'category'        => 'nullable|string|max:100',
            'icon'            => 'nullable|string|max:10',
            'nodes'           => 'sometimes|array',
            'connections'     => 'sometimes|array',
            'trigger_type'    => 'sometimes|string',
            'trigger_config'  => 'sometimes|array',
            'variables'       => 'sometimes|array',
        ]);

        $update = array_intersect_key($validated, array_flip(['name', 'description', 'category', 'icon']));

        $definitionKeys = array_intersect_key($validated, array_flip([
            'nodes', 'connections', 'trigger_type', 'trigger_config', 'variables',
        ]));
        if (! empty($definitionKeys)) {
            $update['flow_definition'] = array_merge($template->flow_definition ?? [], $definitionKeys);
        }

        $template->update($update);

        return response()->json(['data' => $template->fresh(), 'message' => 'Template updated']);
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
        $definition = $template->flow_definition ?? [];

        return response()->json([
            'data' => [
                'template'              => $template,
                'estimated_nodes'       => count($definition['nodes'] ?? []),
                'estimated_connections' => count($definition['connections'] ?? []),
            ],
        ]);
    }
}
