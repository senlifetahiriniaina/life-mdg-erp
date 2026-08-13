<?php

namespace Modules\CRM\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\CRM\Models\Workflow;
use Modules\CRM\Models\WorkflowNode;
use Modules\CRM\Models\WorkflowEdge;

class WorkflowBuilderController
{
    public function index(Request $request): JsonResponse
    {
        $workflows = Workflow::query()
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->trigger_type, fn ($q) => $q->where('trigger_type', $request->trigger_type))
            ->with('owner')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json($workflows);
    }

    public function create(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'description'    => 'nullable|string',
            'trigger_type'   => 'required|in:opportunity_created,contact_updated,manual',
            'trigger_config' => 'nullable|json',
        ]);

        $workflow = Workflow::create(array_merge($validated, [
            'owner_id' => auth()->id(),
            'status'   => 'draft',
        ]));

        return response()->json($workflow, 201);
    }

    public function show(Workflow $workflow): JsonResponse
    {
        $workflow->load(['nodes', 'edges']);

        return response()->json($workflow);
    }

    public function saveWorkflow(Request $request, Workflow $workflow): JsonResponse
    {
        $validated = $request->validate([
            'nodes' => 'required|array',
            'edges' => 'required|array',
            'name'  => 'nullable|string',
        ]);

        $workflow->update(['name' => $validated['name'] ?? $workflow->name]);

        // Delete existing nodes and edges
        $workflow->nodes()->delete();
        $workflow->edges()->delete();

        // Create new nodes
        foreach ($validated['nodes'] as $node) {
            WorkflowNode::create([
                'workflow_id' => $workflow->id,
                'node_id'     => $node['id'],
                'type'        => $node['type'],
                'name'        => $node['name'],
                'config'      => $node['config'] ?? [],
                'position_x'  => $node['positionX'] ?? 0,
                'position_y'  => $node['positionY'] ?? 0,
            ]);
        }

        // Create new edges
        foreach ($validated['edges'] as $edge) {
            WorkflowEdge::create([
                'workflow_id'   => $workflow->id,
                'from_node_id'  => $edge['source'],
                'to_node_id'    => $edge['target'],
                'condition'     => $edge['condition'] ?? null,
            ]);
        }

        return response()->json(['message' => 'Workflow saved', 'workflow' => $workflow->load(['nodes', 'edges'])]);
    }

    public function activate(Workflow $workflow): JsonResponse
    {
        if ($workflow->nodes()->count() === 0) {
            return response()->json(['error' => 'Workflow has no nodes'], 400);
        }

        $workflow->update(['status' => 'active']);

        return response()->json(['message' => 'Workflow activated']);
    }

    public function deactivate(Workflow $workflow): JsonResponse
    {
        $workflow->update(['status' => 'paused']);

        return response()->json(['message' => 'Workflow paused']);
    }

    public function getExecutions(Workflow $workflow): JsonResponse
    {
        $executions = $workflow->executions()
            ->orderBy('started_at', 'desc')
            ->paginate(10);

        return response()->json($executions);
    }
}
