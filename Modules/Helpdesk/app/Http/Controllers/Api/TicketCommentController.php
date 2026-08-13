<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Helpdesk\Models\Ticket;
use Modules\Helpdesk\Models\TicketComment;

/**
 * @group Helpdesk - Ticket Comments
 *
 * Add and manage comments on support tickets.
 */
class TicketCommentController extends Controller
{
    /**
     * List comments for a ticket.
     *
     * @urlParam ticket int required The ticket ID. Example: 1
     */
    public function index(Request $request, Ticket $ticket): JsonResponse
    {
        $query = TicketComment::with('user:id,name,email')
            ->where('ticket_id', $ticket->id);

        // Internal notes only visible to agents/admins
        if (! $request->user()->hasAnyRole(['super-admin', 'admin', 'manager'])) {
            $query->where('is_internal', false);
        }

        return response()->json($query->latest()->paginate(50));
    }

    /**
     * Add a comment to a ticket.
     *
     * @urlParam ticket int required The ticket ID. Example: 1
     *
     * @bodyParam content string required Comment body. Example: We are looking into this issue.
     * @bodyParam is_internal boolean Mark as internal note (agents only). Example: false
     */
    public function store(Request $request, Ticket $ticket): JsonResponse
    {
        $validated = $request->validate([
            'content' => ['required', 'string'],
            'is_internal' => ['nullable', 'boolean'],
        ]);

        // Only agents can add internal notes
        $isInternal = ($validated['is_internal'] ?? false) &&
            $request->user()->hasAnyRole(['super-admin', 'admin', 'manager']);

        $comment = TicketComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'content' => $validated['content'],
            'is_internal' => $isInternal,
        ]);

        return response()->json($comment->load('user:id,name,email'), 201);
    }

    /**
     * Update a comment.
     *
     * @urlParam ticket int required The ticket ID. Example: 1
     * @urlParam comment int required The comment ID. Example: 1
     */
    public function update(Request $request, Ticket $ticket, TicketComment $comment): JsonResponse
    {
        if ($comment->ticket_id !== $ticket->id) {
            abort(404);
        }

        if ($comment->user_id !== $request->user()->id && ! $request->user()->hasAnyRole(['super-admin', 'admin'])) {
            abort(403, 'You can only edit your own comments.');
        }

        $validated = $request->validate([
            'content' => ['required', 'string'],
        ]);

        $comment->update($validated);

        return response()->json($comment->fresh()->load('user:id,name,email'));
    }

    /**
     * Delete a comment.
     *
     * @urlParam ticket int required The ticket ID. Example: 1
     * @urlParam comment int required The comment ID. Example: 1
     */
    public function destroy(Request $request, Ticket $ticket, TicketComment $comment): JsonResponse
    {
        if ($comment->ticket_id !== $ticket->id) {
            abort(404);
        }

        if ($comment->user_id !== $request->user()->id && ! $request->user()->hasAnyRole(['super-admin', 'admin'])) {
            abort(403, 'You can only delete your own comments.');
        }

        $comment->delete();

        return response()->json(null, 204);
    }
}
