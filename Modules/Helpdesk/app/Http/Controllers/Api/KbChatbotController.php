<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Helpdesk\Services\AI\HelpdeskAIService;

/**
 * @group Controllers - Kb Chatbot
 *
 * Manage Kb Chatbot resources.
 */
class KbChatbotController extends Controller
{
    public function __construct(private readonly HelpdeskAIService $service) {}

    public function answer(Request $request)
    {
        $result = $this->service->kbChatbotResponse($request->input('query'));

        return response()->json($result);
    }
}
