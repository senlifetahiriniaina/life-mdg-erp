<?php

declare(strict_types=1);

namespace Modules\CRM\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\CRM\Services\AI\CrmAIService;

/**
 * @group Controllers - Crm Prospecting
 *
 * Manage Crm Prospecting resources.
 */
class CrmProspectingController extends Controller
{
    public function __construct(private readonly CrmAIService $service) {}

    public function generateEmail(Request $request)
    {
        $email = $this->service->draftProspectingEmail($request->contact_id, $request->context ?? '');

        return response()->json($email);
    }

    public function detectDuplicates(Request $request)
    {
        $result = $this->service->detectDuplicates($request->contact_data);

        return response()->json($result);
    }

    public function analyzeSentiment(Request $request)
    {
        $sentiment = $this->service->analyzeConversationSentiment($request->contact_id, $request->conversation ?? $request->text);

        return response()->json($sentiment);
    }
}
