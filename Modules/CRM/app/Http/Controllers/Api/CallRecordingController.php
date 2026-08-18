<?php

declare(strict_types=1);

namespace Modules\CRM\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\CRM\Jobs\SummarizeCallJob;
use Modules\CRM\Models\CallRecording;
use Modules\CRM\Services\VoipService;

/**
 * @group CRM - Call Recordings
 */
class CallRecordingController extends Controller
{
    public function __construct(private readonly VoipService $voipService) {}

    /**
     * Get the recording associated with a call log.
     *
     * @urlParam callId integer required The call log ID. Example: 1
     *
     * @response 200 {"id": 1, "call_id": 1, "status": "ready", "recording_url": "https://..."}
     * @response 404 {"message": "No recording found for this call."}
     */
    public function show(Request $request, int $callId): JsonResponse
    {
        $recording = CallRecording::where('call_id', $callId)
            ->where('company_id', $request->user()->company_id)
            ->latest()
            ->first();

        if (! $recording) {
            return response()->json(['message' => 'No recording found for this call.'], 404);
        }

        $this->authorize('view', $recording);

        return response()->json($recording);
    }

    /**
     * Queue an AI summarization job for a call recording.
     *
     * @urlParam callId integer required The call log ID. Example: 1
     *
     * @response 202 {"message": "Summarization queued.", "call_id": 1}
     */
    public function summarize(Request $request, int $callId): JsonResponse
    {
        $recording = CallRecording::where('call_id', $callId)
            ->where('company_id', $request->user()->company_id)
            ->latest()
            ->first();

        if (! $recording) {
            return response()->json(['message' => 'No recording found for this call.'], 404);
        }

        $this->authorize('summarize', $recording);

        SummarizeCallJob::dispatch($callId);

        return response()->json([
            'message' => 'Summarization queued.',
            'call_id' => $callId,
        ], 202);
    }

    /**
     * Get the AI summary for a call recording.
     *
     * @urlParam callId integer required The call log ID. Example: 1
     *
     * @response 200 {"summary": "...", "action_items": [], "sentiment": "positive", "next_step_suggestion": "..."}
     * @response 404 {"message": "No recording found for this call."}
     * @response 422 {"message": "AI summary not yet generated. Queue it via POST /calls/{id}/summarize."}
     */
    public function getSummary(Request $request, int $callId): JsonResponse
    {
        $recording = CallRecording::where('call_id', $callId)
            ->where('company_id', $request->user()->company_id)
            ->latest()
            ->first();

        if (! $recording) {
            return response()->json(['message' => 'No recording found for this call.'], 404);
        }

        $this->authorize('view', $recording);

        if ($recording->status !== CallRecording::STATUS_READY || ! $recording->ai_summary) {
            return response()->json([
                'message' => 'AI summary not yet generated. Queue it via POST /calls/{id}/summarize.',
                'status'  => $recording->status,
            ], 422);
        }

        return response()->json($recording->ai_summary);
    }
}
