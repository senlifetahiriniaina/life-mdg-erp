<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Helpdesk\Services\AI\HelpdeskAIService;

/**
 * @group Helpdesk - AI
 *
 * AI-powered ticket classification and suggested replies.
 */
class HelpdeskAIController extends Controller
{
    public function __construct(private readonly HelpdeskAIService $ai) {}

    public function categorize(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject' => 'required|string|max:255',
            'description' => 'required|string|max:5000',
        ]);

        return response()->json($this->ai->categorizeTicket($data['subject'], $data['description']));
    }

    public function suggestResponse(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject' => 'required|string|max:255',
            'description' => 'required|string|max:5000',
            'previous_comments' => 'nullable|array',
        ]);

        return response()->json([
            'response' => $this->ai->suggestResponse($data['subject'], $data['description'], $data['previous_comments'] ?? []),
        ]);
    }

    public function summarize(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject' => 'required|string|max:255',
            'description' => 'required|string|max:5000',
            'comments' => 'nullable|array',
        ]);

        return response()->json([
            'summary' => $this->ai->summarizeTicket($data['subject'], $data['description'], $data['comments'] ?? []),
        ]);
    }

    public function predictEscalation(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ticket_id' => ['required', 'integer'],
            'ticket_data' => ['required', 'array'],
        ]);

        return response()->json($this->ai->predictEscalation($data['ticket_id'], $data['ticket_data']));
    }
}
