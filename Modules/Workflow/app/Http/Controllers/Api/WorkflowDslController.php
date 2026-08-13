<?php

declare(strict_types=1);

namespace Modules\Workflow\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Workflow\Exceptions\WorkflowDslParseException;
use Modules\Workflow\Services\WorkflowDslParser;
use Modules\Workflow\Services\WorkflowEngineService;

/**
 * @group Workflow - DSL
 *
 * Parse, validate, and preview DSL (SI/QUAND/ALORS) workflow rules.
 */
class WorkflowDslController extends Controller
{
    public function __construct(
        private WorkflowDslParser $parser,
        private WorkflowEngineService $engine,
    ) {}

    /**
     * Parse DSL text and return the structured definition.
     *
     * @bodyParam dsl string required DSL text in SI/QUAND/ALORS syntax.
     */
    public function parse(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'dsl' => 'required|string',
        ]);

        try {
            $parsed = $this->parser->parse($validated['dsl']);

            return response()->json(['data' => $parsed]);
        } catch (WorkflowDslParseException $e) {
            return response()->json(['message' => 'DSL parse error', 'errors' => [$e->getMessage()]], 422);
        }
    }

    /**
     * Validate DSL text without executing it.
     *
     * @bodyParam dsl string required DSL text in SI/QUAND/ALORS syntax.
     */
    public function validate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'dsl' => 'required|string',
        ]);

        $result = $this->parser->validate($validated['dsl']);

        $valid = empty($result['errors'] ?? []);

        return response()->json([
            'valid'    => $valid,
            'errors'   => $result['errors'] ?? [],
            'warnings' => $result['warnings'] ?? [],
        ], $valid ? 200 : 422);
    }

    /**
     * Convert DSL text to JSON definition.
     *
     * @bodyParam dsl string required DSL text in SI/QUAND/ALORS syntax.
     */
    public function toJson(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'dsl' => 'required|string',
        ]);

        try {
            $json = $this->parser->toJson($validated['dsl']);

            return response()->json(['data' => json_decode($json, true)]);
        } catch (WorkflowDslParseException $e) {
            return response()->json(['message' => 'DSL parse error', 'errors' => [$e->getMessage()]], 422);
        }
    }

    /**
     * Convert a JSON definition back to DSL text.
     *
     * @bodyParam definition array required JSON workflow definition.
     */
    public function fromJson(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'definition' => 'required|array',
        ]);

        $dsl = $this->parser->fromJson($validated['definition']);

        return response()->json(['data' => ['dsl' => $dsl]]);
    }

    /**
     * Preview the execution plan for a DSL rule (dry-run, no side effects).
     *
     * @bodyParam dsl string required DSL text in SI/QUAND/ALORS syntax.
     * @bodyParam context array Optional context variables for the preview.
     */
    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'dsl'     => 'required|string',
            'context' => 'sometimes|array',
        ]);

        try {
            $parsed = $this->parser->parse($validated['dsl']);

            return response()->json([
                'data' => [
                    'definition'     => $parsed,
                    'trigger'        => $parsed['trigger'] ?? null,
                    'conditions'     => $parsed['conditions'] ?? [],
                    'actions'        => $parsed['actions'] ?? [],
                    'estimated_steps' => count($parsed['actions'] ?? []),
                    'dry_run'        => true,
                ],
            ]);
        } catch (WorkflowDslParseException $e) {
            return response()->json(['message' => 'DSL parse error', 'errors' => [$e->getMessage()]], 422);
        }
    }
}
