<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Helpdesk\Models\ChatSession;
use Modules\Helpdesk\Services\LiveChatService;

/**
 * @group Helpdesk - Live Chat
 */
class ChatController extends Controller
{
    public function __construct(private readonly LiveChatService $chatService) {}

    /**
     * Get messages for a session (poll).
     */
    public function getMessages(Request $request, ChatSession $session): JsonResponse
    {
        $since = $request->query('since');

        $query = $session->messages()->orderBy('created_at');
        if ($since) {
            $query->where('created_at', '>', $since);
        }

        $messages = $query->get();

        return response()->json([
            'messages' => $messages,
            'session_status' => $session->status,
            'queue_position' => $this->chatService->getQueuePosition($session),
        ]);
    }

    /**
     * Close a chat session.
     */
    public function closeSession(ChatSession $session): JsonResponse
    {
        if ($session->status === 'closed') {
            return response()->json(['message' => 'Session already closed.'], 422);
        }

        $this->chatService->closeSession($session);

        return response()->json(['message' => 'Session closed.', 'session' => $session->fresh()]);
    }

    /**
     * List waiting sessions queue (auth required).
     */
    public function queue(): JsonResponse
    {
        $waiting = ChatSession::where('status', 'waiting')
            ->orderBy('started_at')
            ->get();

        $active = ChatSession::where('status', 'active')
            ->with('assignedAgent:id,name')
            ->orderBy('started_at')
            ->get();

        return response()->json([
            'waiting' => $waiting,
            'active' => $active,
            'waiting_count' => $waiting->count(),
        ]);
    }
}
