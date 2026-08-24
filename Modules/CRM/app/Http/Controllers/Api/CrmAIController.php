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
        // Chantier 38.3: accepts both `text` and `conversation` — this endpoint used to be
        // shadowed by the unvalidated CrmProspectingController::analyzeSentiment(), which read
        // `$request->conversation ?? $request->text` with no requirement at all. Repointed here
        // (see routes/api.php) onto this real, validated method, but kept both field names so
        // no caller relying on either name silently 422s now that validation is real.
        $validated = $request->validate([
            'contact_id' => 'required|integer',
            'text' => 'required_without:conversation|nullable|string',
            'conversation' => 'required_without:text|nullable|string',
        ]);
        $text = $validated['text'] ?? $validated['conversation'];
        $sentiment = $this->aiService->analyzeConversationSentiment($validated['contact_id'], $text);

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

    /**
     * Chantier 38.3: passed the bare `opportunity_id` int where `suggestNextAction(array
     * $opportunityData)` requires an array — the exact same class of bug as
     * draftFollowUp()'s undefined-method fatal above, here a TypeError instead, confirmed
     * empirically via tinker on every real call before this fix. Zero test coverage existed
     * for this route.
     */
    public function suggestNextAction(Request $request)
    {
        $validated = $request->validate([
            'opportunity_id' => 'required|integer',
        ]);
        $suggestion = $this->aiService->suggestNextAction(['opportunity_id' => $validated['opportunity_id']]);

        return response()->json($suggestion);
    }

    /**
     * Chantier 38.3: called `$this->aiService->draftFollowUpEmail(...)` — a method that has
     * never existed anywhere on CrmAIService (the real method is `draftFollowUp(array
     * $contactData, string $context)`) — a guaranteed fatal `Error: Call to undefined method`
     * on every real call to this endpoint, confirmed empirically via tinker before this fix.
     * Zero test coverage existed for this route at all, which is exactly why it went
     * undetected. Fixed to call the real method name with the array shape it actually expects
     * (matching detectDuplicates()'s established array-payload convention on this same
     * service, rather than the bare int the old, wrong call passed).
     */
    public function draftFollowUp(Request $request)
    {
        $validated = $request->validate([
            'contact_id' => 'required|integer',
            'context' => 'nullable|string',
        ]);
        $draft = $this->aiService->draftFollowUp(['contact_id' => $validated['contact_id']], $validated['context'] ?? '');

        // Matches suggestNextAction()'s own convention on this controller — draftFollowUp()
        // also returns a bare string, wrapped as-is rather than inventing a new shape no real
        // caller has ever depended on (zero test/frontend coverage existed for this route).
        return response()->json($draft);
    }
}
