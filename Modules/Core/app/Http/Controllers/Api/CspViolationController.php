<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Models\CspViolation;
use Modules\Core\Services\CspViolationLogger;

/**
 * @group Core - CSP Violations
 *
 * Chantier 8.3 found CspViolation/CspViolationLogger/CspViolationPolicy fully
 * written and tested, but with no controller/route anywhere — browsers had
 * nowhere real to POST their Content-Security-Policy report-uri/report-to
 * violation reports to, and there was no authenticated endpoint to read them
 * back.
 */
class CspViolationController extends Controller
{
    public function __construct(private readonly CspViolationLogger $logger) {}

    /**
     * Ingest a CSP violation report from the browser. Public/unauthenticated
     * by design — browsers send this automatically per the CSP spec, with no
     * session context of their own.
     *
     * POST /api/v1/core/csp/report
     */
    public function report(Request $request): JsonResponse
    {
        $report = $request->input('csp-report', $request->all());

        $this->logger->logViolation(
            is_array($report) ? $report : [],
            $request->user()?->id,
            $request->user()?->tenant_id,
            $request->string('module')->toString() ?: null,
        );

        return response()->json(null, 204);
    }

    /**
     * List CSP violations.
     *
     * GET /api/v1/core/csp/violations
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CspViolation::class);

        $violations = CspViolation::query()
            ->when($request->filled('severity'), fn ($q) => $q->bySeverity($request->input('severity')))
            ->when($request->filled('module'), fn ($q) => $q->forModule($request->string('module')->toString()))
            ->when($request->boolean('unresolved_only'), fn ($q) => $q->unresolved())
            ->latest()
            ->paginate($request->integer('per_page', 25));

        return response()->json($violations);
    }

    /**
     * Show a single CSP violation.
     *
     * GET /api/v1/core/csp/violations/{violation}
     */
    public function show(CspViolation $violation): JsonResponse
    {
        $this->authorize('view', $violation);

        return response()->json(['data' => $violation]);
    }

    /**
     * Mark a CSP violation as resolved.
     *
     * POST /api/v1/core/csp/violations/{violation}/resolve
     */
    public function resolve(CspViolation $violation): JsonResponse
    {
        $this->authorize('update', $violation);

        $violation->resolve();

        return response()->json(['data' => $violation->fresh(), 'message' => 'Violation resolved']);
    }

    /**
     * Aggregated violation statistics.
     *
     * GET /api/v1/core/csp/stats
     */
    public function stats(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CspViolation::class);

        return response()->json([
            'data' => $this->logger->getViolationStats(
                $request->input('from'),
                $request->input('to'),
                $request->string('tenant_id')->toString() ?: null,
            ),
        ]);
    }
}
