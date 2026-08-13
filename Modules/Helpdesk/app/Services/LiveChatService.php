<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Modules\Helpdesk\Models\ChatMessage;
use Modules\Helpdesk\Models\ChatSession;
use Modules\Helpdesk\Models\Ticket;

class LiveChatService
{
    /**
     * Start a new chat session for a visitor.
     *
     * @param  array<string,mixed>  $metadata
     */
    public function startSession(string $visitorId, array $metadata = []): ChatSession
    {
        return ChatSession::create([
            'visitor_id' => $visitorId,
            'visitor_name' => $metadata['visitor_name'] ?? null,
            'visitor_email' => $metadata['visitor_email'] ?? null,
            'status' => 'waiting',
            'started_at' => now(),
            'channel' => $metadata['channel'] ?? 'web',
            'metadata' => $metadata,
        ]);
    }

    /**
     * Assign a chat session to an agent.
     */
    public function assignAgent(ChatSession $session, User $agent): void
    {
        $session->update([
            'assigned_agent_id' => $agent->id,
            'status' => 'active',
        ]);

        $this->sendMessage($session, 'bot', null, "Agent {$agent->name} joined the chat.");
    }

    /**
     * Send a message in a chat session.
     */
    public function sendMessage(
        ChatSession $session,
        string $senderType,
        ?int $senderId,
        string $message,
        string $type = 'text'
    ): ChatMessage {
        /** @var ChatMessage $chatMessage */
        $chatMessage = $session->messages()->create([
            'sender_type' => $senderType,
            'sender_id' => $senderId,
            'message' => $message,
            'type' => $type,
        ]);

        return $chatMessage;
    }

    /**
     * Close a chat session, optionally linking it to a ticket.
     */
    public function closeSession(ChatSession $session, ?int $ticketId = null): void
    {
        $session->update([
            'status' => 'closed',
            'closed_at' => now(),
            'ticket_id' => $ticketId,
        ]);

        $this->sendMessage($session, 'bot', null, 'Chat session closed.', 'system');
    }

    /**
     * Convert a chat session to a support ticket.
     */
    public function convertToTicket(ChatSession $session): Ticket
    {
        /** @var Collection<int, ChatMessage> $messages */
        $messages = $session->messages()
            ->where('sender_type', '!=', 'bot')
            ->orderBy('created_at')
            ->get();

        $visitorName = $session->visitor_name ?? 'Visitor';
        $description = $messages->map(function (ChatMessage $msg) use ($visitorName): string {
            $sender = $msg->sender_type === 'visitor' ? $visitorName : 'Agent';

            return "[{$msg->created_at?->format('H:i')}] {$sender}: {$msg->message}";
        })->implode("\n");

        $ticket = Ticket::create([
            'subject' => 'Live chat from '.($session->visitor_name ?? $session->visitor_id),
            'description' => $description,
            'channel' => 'web',
            'priority' => 'medium',
            'status' => 'open',
            'source_ref' => 'chat:'.$session->id,
        ]);

        $this->closeSession($session, $ticket->id);

        return $ticket;
    }

    /**
     * Get the queue position of a waiting session.
     */
    public function getQueuePosition(ChatSession $session): int
    {
        if ($session->status !== 'waiting') {
            return 0;
        }

        return ChatSession::where('status', 'waiting')
            ->where('started_at', '<=', $session->started_at)
            ->count();
    }
}
