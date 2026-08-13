<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
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
     * Start a new chat session (visitor, no auth required).
     */
    public function startSession(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'visitor_id' => ['required', 'string', 'max:128'],
            'visitor_name' => ['nullable', 'string', 'max:120'],
            'visitor_email' => ['nullable', 'email', 'max:255'],
            'channel' => ['nullable', 'in:web,widget'],
        ]);

        $metadata = array_filter([
            'visitor_name' => $validated['visitor_name'] ?? null,
            'visitor_email' => $validated['visitor_email'] ?? null,
            'channel' => $validated['channel'] ?? 'web',
        ]);

        $session = $this->chatService->startSession($validated['visitor_id'], $metadata);

        return response()->json([
            'session' => $session,
            'queue_position' => $this->chatService->getQueuePosition($session),
        ], 201);
    }

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
     * Send a message in a session.
     */
    public function sendMessage(Request $request, ChatSession $session): JsonResponse
    {
        $validated = $request->validate([
            'sender_type' => ['required', 'in:visitor,agent,bot'],
            'message' => ['required', 'string', 'max:5000'],
            'type' => ['nullable', 'in:text,image,file,system'],
        ]);

        if ($session->status === 'closed') {
            return response()->json(['message' => 'Chat session is closed.'], 422);
        }

        $senderId = null;
        if ($request->user() && $validated['sender_type'] === 'agent') {
            $senderId = $request->user()->id;
        }

        $message = $this->chatService->sendMessage(
            $session,
            $validated['sender_type'],
            $senderId,
            $validated['message'],
            $validated['type'] ?? 'text'
        );

        return response()->json($message, 201);
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
     * Convert chat session to a ticket (auth required).
     */
    public function convertToTicket(ChatSession $session): JsonResponse
    {
        if ($session->status === 'closed' && $session->ticket_id) {
            return response()->json(['message' => 'Session already converted.'], 422);
        }

        $ticket = $this->chatService->convertToTicket($session);

        return response()->json(['ticket' => $ticket, 'session' => $session->fresh()], 201);
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

    /**
     * Assign a session to the authenticated agent.
     */
    public function assign(Request $request, ChatSession $session): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($session->status === 'closed') {
            return response()->json(['message' => 'Cannot assign a closed session.'], 422);
        }

        $this->chatService->assignAgent($session, $user);

        return response()->json($session->fresh()->load('assignedAgent:id,name'));
    }
}
