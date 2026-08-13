<?php

declare(strict_types=1);

namespace Modules\CRM\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\CRM\Services\AI\CrmAIService;

/**
 * @group Controllers - Crm AI
 *
 * Manage Crm AI resources.
 */
class CrmAIController extends Controller
{
    public function __construct(private readonly CrmAIService $aiService) {}

    public function generateProspectingEmail(Request $request)
    {
        $validated = $request->validate([
            'contact_id' => 'required|integer',
            'context' => 'nullable|string',
        ]);
        $email = $this->aiService->draftProspectingEmail($validated['contact_id'], $validated['context'] ?? '');

        return response()->json($email);
    }

    public function analyzeSentiment(Request $request)
    {
        $validated = $request->validate([
            'contact_id' => 'required|integer',
            'text' => 'required|string',
        ]);
        $sentiment = $this->aiService->analyzeConversationSentiment($validated['contact_id'], $validated['text']);

        return response()->json($sentiment);
    }

    public function detectDuplicates(Request $request)
    {
        $validated = $request->validate([
            'contact_data' => 'required|array',
        ]);
        $duplicates = $this->aiService->detectDuplicates($validated['contact_data']);

        return response()->json($duplicates);
    }

    public function transcribeCall(Request $request)
    {
        $validated = $request->validate([
            'contact_id' => 'required|integer',
            'transcription' => 'required|string',
        ]);
        $activity = $this->aiService->transcribeCallToActivity($validated['transcription'], $validated['contact_id']);

        return response()->json($activity);
    }

    public function scoreLeads(Request $request)
    {
        $validated = $request->validate([
            'leads' => 'required|array',
        ]);
        $scores = $this->aiService->scoreLeads($validated['leads']);

        return response()->json($scores);
    }

    public function suggestNextAction(Request $request)
    {
        $validated = $request->validate([
            'opportunity_id' => 'required|integer',
        ]);
        $suggestion = $this->aiService->suggestNextAction($validated['opportunity_id']);

        return response()->json($suggestion);
    }

    public function draftFollowUp(Request $request)
    {
        $validated = $request->validate([
            'contact_id' => 'required|integer',
            'context' => 'nullable|string',
        ]);
        $draft = $this->aiService->draftFollowUpEmail($validated['contact_id'], $validated['context'] ?? '');

        return response()->json($draft);
    }
}
