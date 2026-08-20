<?php

declare(strict_types=1);

namespace Modules\Messaging\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Messaging\Models\Conversation;

class ConversationController extends Controller
{
    /**
     * Company-scoped user directory for the "new conversation" recipient
     * picker — deliberately its own lightweight endpoint rather than
     * repurposing HR's EmployeeResource (which never exposes the linked
     * User at all) or Core's broader user-listing endpoints.
     */
    public function users(Request $request): JsonResponse
    {
        $users = User::where('company_id', $request->user()->company_id)
            ->where('id', '!=', $request->user()->id)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return response()->json(['data' => $users]);
    }

    public function index(Request $request): JsonResponse
    {
        $conversations = $request->user()->conversations()
            ->withCount(['messages'])
            ->with(['users:id,name,email', 'lastMessage'])
            ->latest('updated_at')
            ->get()
            ->map(function (Conversation $c) use ($request) {
                $pivot = $c->participants()->where('user_id', $request->user()->id)->first();
                $unread = $c->messages()
                    ->when($pivot?->last_read_at, fn ($q) => $q->where('created_at', '>', $pivot->last_read_at))
                    ->count();

                return [
                    'id' => $c->id,
                    'type' => $c->type,
                    'name' => $c->name,
                    'users' => $c->users,
                    'last_message' => $c->lastMessage->first(),
                    'unread_count' => $unread,
                ];
            });

        return response()->json(['data' => $conversations]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $participantIds = array_unique(array_merge($validated['user_ids'], [$request->user()->id]));
        $type = count($participantIds) > 2 ? 'group' : 'direct';

        // For a direct conversation between the same two users, reuse the
        // existing one instead of creating a duplicate thread every time.
        // Filtered in PHP rather than a SQL HAVING clause — SQLite (this app's
        // dev/test driver) rejects HAVING on a withCount() alias without an
        // explicit GROUP BY, a real "General error: 1 HAVING clause on a
        // non-aggregate query" confirmed empirically while testing this route.
        if ($type === 'direct') {
            $existing = Conversation::where('type', 'direct')
                ->whereHas('participants', fn ($q) => $q->where('user_id', $participantIds[0]))
                ->whereHas('participants', fn ($q) => $q->where('user_id', $participantIds[1]))
                ->withCount('participants')
                ->get()
                ->firstWhere('participants_count', 2);

            if ($existing) {
                return response()->json(['data' => $existing->load('users:id,name,email')], 200);
            }
        }

        $conversation = Conversation::create([
            'company_id' => $request->user()->company_id,
            'type' => $type,
            'name' => $validated['name'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        foreach ($participantIds as $userId) {
            $conversation->participants()->create(['user_id' => $userId]);
        }

        return response()->json(['data' => $conversation->load('users:id,name,email')], 201);
    }

    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        return response()->json(['data' => $conversation->load('users:id,name,email')]);
    }
}
