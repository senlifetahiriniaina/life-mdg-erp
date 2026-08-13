<?php

declare(strict_types=1);

namespace Modules\Workflow\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Workflow\Services\CodeNodeService;

/**
 * REST controller for the sandboxed expression / code node.
 *
 * Routes (all under /v1/workflow/code-node):
 *   POST /validate  — check a code block for syntax / sandbox safety
 *   POST /execute   — run the code against a context and return output
 */
class CodeNodeController extends Controller
{
    public function __construct(
        private readonly CodeNodeService $service,
    ) {}

    /**
     * POST /v1/workflow/code-node/validate
     *
     * Body:
     *   {
     *     "language": "expression" | "python_safe",
     *     "code": "..."
     *   }
     */
    public function validate(Request $request): JsonResponse
    {
        $request->validate([
            'language' => 'required|string|in:expression,python_safe',
            'code'     => 'required|string',
        ]);

        $result = $this->service->validate(
            language: $request->input('language'),
            code:     $request->input('code'),
        );

        $status = $result['valid'] ? 200 : 422;
        return response()->json($result, $status);
    }

    /**
     * POST /v1/workflow/code-node/execute
     *
     * Body:
     *   {
     *     "language": "expression" | "python_safe",
     *     "code": "...",
     *     "context": { ... },     // optional
     *     "timeout": 5            // optional, seconds, max 10
     *   }
     */
    public function execute(Request $request): JsonResponse
    {
        $request->validate([
            'language' => 'required|string|in:expression,python_safe',
            'code'     => 'required|string',
            'context'  => 'sometimes|array',
            'timeout'  => 'sometimes|integer|min:1|max:10',
        ]);

        $result = $this->service->execute(
            language: $request->input('language'),
            code:     $request->input('code'),
            context:  $request->input('context', []),
            timeout:  $request->input('timeout', 5),
        );

        $status = $result['error'] ? 422 : 200;
        return response()->json($result, $status);
    }
}
