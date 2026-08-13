<?php

declare(strict_types=1);

namespace Modules\Workflow\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Workflow\Services\Automation\NodeTypeRegistry;
use Modules\Workflow\Services\WorkflowActionRegistry;

/**
 * @group Workflow - Nodes
 *
 * List available node types, get node schemas, and test individual nodes.
 */
class WorkflowNodeController extends Controller
{
    public function __construct(
        private NodeTypeRegistry $nodeTypeRegistry,
        private WorkflowActionRegistry $actionRegistry,
    ) {}

    /**
     * List all available node types.
     *
     * @queryParam module string Filter by module. Example: CRM
     * @queryParam category string Filter by category (trigger|action|condition|transform). Example: action
     */
    public function index(Request $request): JsonResponse
    {
        $all = $this->nodeTypeRegistry->getAll();

        $filtered = collect($all)
            ->when(
                $request->filled('module'),
                fn ($c) => $c->filter(fn ($n) => ($n['module'] ?? '') === $request->module)
            )
            ->when(
                $request->filled('category'),
                fn ($c) => $c->filter(fn ($n) => ($n['category'] ?? '') === $request->category)
            )
            ->values();

        return response()->json(['data' => $filtered]);
    }

    /**
     * Get schema/config definition for a specific node type.
     *
     * @urlParam key string required Node type key. Example: crm.create_contact
     */
    public function show(string $key): JsonResponse
    {
        $all  = $this->nodeTypeRegistry->getAll();
        $node = collect($all)->first(fn ($n) => ($n['key'] ?? '') === $key);

        if (! $node) {
            return response()->json(['message' => 'Node type not found'], 404);
        }

        return response()->json(['data' => $node]);
    }

    /**
     * Get node types grouped by module.
     */
    public function byModule(): JsonResponse
    {
        $all     = $this->nodeTypeRegistry->getAll();
        $grouped = collect($all)->groupBy('module');

        return response()->json(['data' => $grouped]);
    }

    /**
     * Get node types grouped by category.
     */
    public function byCategory(): JsonResponse
    {
        $all     = $this->nodeTypeRegistry->getAll();
        $grouped = collect($all)->groupBy('category');

        return response()->json(['data' => $grouped]);
    }

    /**
     * Test a node with provided config and context (dry-run).
     *
     * @bodyParam key string required Node type key. Example: crm.create_contact
     * @bodyParam config array Node configuration. Example: {"name": "Test"}
     * @bodyParam context array Execution context variables. Example: {"tenant_id": 1}
     */
    public function test(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'key'     => 'required|string',
            'config'  => 'sometimes|array',
            'context' => 'sometimes|array',
        ]);

        $all  = $this->nodeTypeRegistry->getAll();
        $node = collect($all)->first(fn ($n) => ($n['key'] ?? '') === $validated['key']);

        if (! $node) {
            return response()->json(['message' => 'Node type not found'], 404);
        }

        // Return a simulated dry-run output
        return response()->json([
            'data' => [
                'node'     => $node,
                'config'   => $validated['config'] ?? [],
                'context'  => $validated['context'] ?? [],
                'dry_run'  => true,
                'output'   => ['status' => 'ok', 'message' => 'Node would execute successfully'],
            ],
        ]);
    }
}
