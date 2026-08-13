<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Helpdesk\Models\BotDeflection;
use Modules\Helpdesk\Services\AnswerBotService;

/**
 * @group Controllers - Answer Bot
 *
 * Manage Answer Bot resources.
 */
class AnswerBotController extends Controller
{
    public function __construct(private readonly AnswerBotService $service) {}

    /**
     * Public endpoint — no auth required.
     */
    public function ask(Request $request): JsonResponse
    {
        $data = $request->validate([
            'question' => 'required|string|max:500',
            'session_id' => 'nullable|string|max:100',
        ]);

        $result = $this->service->findAnswer($data['question']);

        return response()->json([
            'question' => $data['question'],
            'articles' => $result['articles'],
            'confidence' => $result['confidence'],
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    /**
     * Record whether the bot answer was useful.
     */
    public function deflect(Request $request): JsonResponse
    {
        $data = $request->validate([
            'question' => 'required|string|max:500',
            'matched_article_id' => 'nullable|integer',
            'deflected' => 'required|boolean',
            'ticket_created' => 'nullable|boolean',
            'session_id' => 'nullable|string|max:100',
        ]);

        $deflection = BotDeflection::create([
            'question' => $data['question'],
            'matched_article_id' => $data['matched_article_id'] ?? null,
            'deflected' => $data['deflected'],
            'ticket_created' => $data['ticket_created'] ?? false,
            'session_id' => $data['session_id'] ?? null,
        ]);

        return response()->json($deflection, 201);
    }
}
