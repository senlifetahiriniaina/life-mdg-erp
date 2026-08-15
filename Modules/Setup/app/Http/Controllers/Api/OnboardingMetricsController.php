<?php

declare(strict_types=1);

namespace Modules\Setup\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Modules\Setup\Models\OnboardingSession;
use Modules\Setup\Services\OnboardingMetricsService;
use Throwable;

/**
 * OnboardingMetricsController
 *
 * REST API for tracking the 5-step onboarding wizard funnel.
 * Powers the "Simplicity First" KPI dashboard: target < 5 minutes.
 *
 * All routes are protected by auth:sanctum middleware.
 * Tenant isolation is enforced on every read/write operation.
 */
class OnboardingMetricsController extends Controller
{
    public function __construct(
        private readonly OnboardingMetricsService $metricsService,
    ) {}

    // -----------------------------------------------------------------------
    // POST /api/v1/setup/onboarding/start
    // -----------------------------------------------------------------------

    /**
     * Start a new onboarding session.
     * Call this when the user arrives on Step 1 of the wizard.
     */
    public function start(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'source_type' => ['required', Rule::in(['file_csv', 'file_excel', 'file_pdf', 'db_migration', 'manual'])],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $tenantId   = $this->tenantId($request);
        $userId     = (int) $request->user()->id;
        $sourceType = (string) $request->input('source_type');

        try {
            $session = $this->metricsService->startSession($tenantId, $userId, $sourceType);

            return response()->json([
                'data'    => $session,
                'message' => 'Onboarding session started.',
            ], 201);
        } catch (Throwable $e) {
            return response()->json(['message' => 'Failed to start session: ' . $e->getMessage()], 500);
        }
    }

    // -----------------------------------------------------------------------
    // POST /api/v1/setup/onboarding/{id}/step
    // -----------------------------------------------------------------------

    /**
     * Record a step-level event for an active session.
     * Events: started | completed | back | skipped | error
     */
    public function recordStep(Request $request, int $id): JsonResponse
    {
        $session = $this->findSessionForTenant($request, $id);
        if ($session === null) {
            return response()->json(['message' => 'Onboarding session not found.'], 404);
        }

        if ($session->completed_at !== null || $session->abandoned_at !== null) {
            return response()->json(['message' => 'Session is already closed.'], 409);
        }

        $validator = Validator::make($request->all(), [
            'step'             => 'required|integer|min:1|max:5',
            'event'            => ['required', Rule::in(['started', 'completed', 'back', 'skipped', 'error'])],
            'duration_seconds' => 'nullable|integer|min:0',
            'metadata'         => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $this->metricsService->recordStep(
                session: $session,
                step: (int) $request->input('step'),
                event: (string) $request->input('event'),
                durationSeconds: (int) ($request->input('duration_seconds', 0)),
                metadata: (array) ($request->input('metadata', [])),
            );

            return response()->json([
                'data'    => $session->refresh(),
                'message' => 'Step event recorded.',
            ]);
        } catch (Throwable $e) {
            return response()->json(['message' => 'Failed to record step: ' . $e->getMessage()], 500);
        }
    }

    // -----------------------------------------------------------------------
    // POST /api/v1/setup/onboarding/{id}/complete
    // -----------------------------------------------------------------------

    /**
     * Mark the session as successfully completed.
     * Records rows_imported, ai_mapping_used, and calculates total_duration_seconds.
     */
    public function complete(Request $request, int $id): JsonResponse
    {
        $session = $this->findSessionForTenant($request, $id);
        if ($session === null) {
            return response()->json(['message' => 'Onboarding session not found.'], 404);
        }

        if ($session->completed_at !== null) {
            return response()->json(['message' => 'Session is already completed.'], 409);
        }

        if ($session->abandoned_at !== null) {
            return response()->json(['message' => 'Session was abandoned and cannot be completed.'], 409);
        }

        $validator = Validator::make($request->all(), [
            'rows_imported'           => 'nullable|integer|min:0',
            'ai_mapping_used'         => 'nullable|boolean',
            'ai_mapping_accepted_percent' => 'nullable|numeric|min:0|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $this->metricsService->completeSession(
                session: $session,
                rowsImported: (int) ($request->input('rows_imported', 0)),
                aiUsed: (bool) ($request->input('ai_mapping_used', false)),
                aiAcceptedPercent: $request->has('ai_mapping_accepted_percent')
                    ? (float) $request->input('ai_mapping_accepted_percent')
                    : null,
            );

            return response()->json([
                'data'              => $session->refresh(),
                'under_5_min'       => $session->isCompletedUnder5Min(),
                'duration_minutes'  => $session->getDurationMinutes(),
                'message'           => $session->isCompletedUnder5Min()
                    ? 'Onboarding completed in under 5 minutes!'
                    : 'Onboarding completed.',
            ]);
        } catch (Throwable $e) {
            return response()->json(['message' => 'Failed to complete session: ' . $e->getMessage()], 500);
        }
    }

    // -----------------------------------------------------------------------
    // POST /api/v1/setup/onboarding/{id}/abandon
    // -----------------------------------------------------------------------

    /**
     * Mark the session as abandoned at the given step.
     * Used when the user closes the wizard before completing it.
     */
    public function abandon(Request $request, int $id): JsonResponse
    {
        $session = $this->findSessionForTenant($request, $id);
        if ($session === null) {
            return response()->json(['message' => 'Onboarding session not found.'], 404);
        }

        if ($session->completed_at !== null) {
            return response()->json(['message' => 'Session is already completed.'], 409);
        }

        if ($session->abandoned_at !== null) {
            return response()->json(['message' => 'Session is already abandoned.'], 409);
        }

        $validator = Validator::make($request->all(), [
            'at_step' => 'required|integer|min:1|max:5',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $this->metricsService->abandonSession(
                session: $session,
                atStep: (int) $request->input('at_step'),
            );

            return response()->json([
                'data'    => $session->refresh(),
                'message' => 'Session marked as abandoned.',
            ]);
        } catch (Throwable $e) {
            return response()->json(['message' => 'Failed to abandon session: ' . $e->getMessage()], 500);
        }
    }

    // -----------------------------------------------------------------------
    // GET /api/v1/setup/onboarding/stats
    // -----------------------------------------------------------------------

    /**
     * Get funnel statistics for the authenticated tenant.
     * Optional query param: days (integer, default 30).
     */
    public function stats(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'days' => 'nullable|integer|min:1|max:365',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $tenantId = $this->tenantId($request);
        $days     = (int) ($request->input('days', 30));

        $stats = $this->metricsService->getStats($tenantId, $days);

        return response()->json([
            'data'   => $stats,
            'period' => ['days' => $days],
        ]);
    }

    // -----------------------------------------------------------------------
    // GET /api/v1/setup/onboarding/stats/export
    // -----------------------------------------------------------------------

    /**
     * Export all onboarding sessions for the tenant as CSV.
     * Optional query param: days (integer, default 30).
     */
    public function exportCsv(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $tenantId = $this->tenantId($request);
        $days     = (int) ($request->input('days', 30));
        $since    = now()->subDays($days)->startOfDay();

        $sessions = OnboardingSession::forTenant($tenantId)
            ->where('started_at', '>=', $since)
            ->orderByDesc('started_at')
            ->get();

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="onboarding_sessions_' . now()->format('Y_m_d') . '.csv"',
        ];

        $columns = [
            'id', 'user_id', 'started_at', 'completed_at', 'abandoned_at',
            'current_step', 'total_duration_seconds', 'source_type',
            'rows_imported', 'ai_mapping_used', 'ai_mapping_accepted_percent',
            'errors_count',
        ];

        return response()->streamDownload(function () use ($sessions, $columns) {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            // UTF-8 BOM for Excel compatibility
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $columns);

            foreach ($sessions as $session) {
                fputcsv($out, [
                    $session->id,
                    $session->user_id,
                    $session->started_at?->toIso8601String(),
                    $session->completed_at?->toIso8601String(),
                    $session->abandoned_at?->toIso8601String(),
                    $session->current_step,
                    $session->total_duration_seconds,
                    $session->source_type,
                    $session->rows_imported,
                    $session->ai_mapping_used ? '1' : '0',
                    $session->ai_mapping_accepted_percent,
                    $session->errors_count,
                ]);
            }

            fclose($out);
        }, 'onboarding_sessions_' . now()->format('Y_m_d') . '.csv', $headers);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function tenantId(Request $request): int
    {
        return (int) ($request->user()?->company_id
            ?? $request->header('X-Company-ID')
            ?? 0);
    }

    private function findSessionForTenant(Request $request, int $id): ?OnboardingSession
    {
        return OnboardingSession::forTenant($this->tenantId($request))->find($id);
    }
}
