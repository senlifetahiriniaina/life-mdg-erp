<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Helpdesk\Models\ChatSession;
use Modules\Helpdesk\Services\LiveChatService;

/**
 * @group Controllers - Live Chat
 *
 * Manage Live Chat resources.
 */
class LiveChatController extends Controller
{
    public function __construct(private readonly LiveChatService $chatService) {}

    /**
     * Start a new visitor chat session (no auth required).
     */
    public function startSession(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'visitor_id' => 'required|string|max:255',
            'visitor_name' => 'nullable|string|max:255',
            'visitor_email' => 'nullable|email|max:255',
            'channel' => 'nullable|string|max:50',
        ]);

        $session = $this->chatService->startSession($validated['visitor_id'], $validated);

        return response()->json(['session' => $session], 201);
    }

    /**
     * Send a message to a chat session.
     */
    public function sendMessage(Request $request, ChatSession $session): JsonResponse
    {
        $validated = $request->validate([
            'sender_type' => 'required|string|in:visitor,agent,bot',
            'message' => 'required|string',
            'type' => 'nullable|string|in:text,image,file,system',
        ]);

        $senderId = $request->user()?->id;

        $message = $this->chatService->sendMessage(
            $session,
            $validated['sender_type'],
            $senderId,
            $validated['message'],
            $validated['type'] ?? 'text',
        );

        return response()->json($message, 201);
    }

    /**
     * Assign the authenticated agent to a waiting session.
     */
    public function assignAgent(Request $request, ChatSession $session): JsonResponse
    {
        $agent = $request->user();
        $this->chatService->assignAgent($session, $agent);
        $session->refresh();

        return response()->json($session);
    }

    /**
     * Convert a chat session to a helpdesk ticket.
     */
    public function convertToTicket(Request $request, ChatSession $session): JsonResponse
    {
        $ticket = $this->chatService->convertToTicket($session);
        $session->refresh();

        return response()->json(['ticket' => $ticket, 'session' => $session], 201);
    }
}
