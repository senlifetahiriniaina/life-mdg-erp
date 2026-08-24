<?php

declare(strict_types=1);

namespace Modules\Messaging\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $userId = $request->user()->id;

        $conversations = $request->user()->conversations()
            ->withCount(['messages'])
            ->with(['users:id,name,email', 'lastMessage'])
            ->latest('updated_at')
            ->get();

        // Chantier 32.28: replaces 2 queries PER conversation (a redundant
        // participants() re-lookup — the pivot's last_read_at is already
        // loaded for free via conversations()'s own withPivot() — plus a
        // per-row unread COUNT) with a single grouped query for the whole
        // page. Confirmed empirically before this fix: 5 conversations cost
        // 16 total queries, 10 of them this exact N+1 pair. Also fixes a
        // real correctness bug found the same way: the old per-row COUNT
        // never excluded the caller's own messages, so a brand-new
        // conversation containing only a message the caller themselves just
        // sent (participant row created with last_read_at still NULL) showed
        // as unread to its own sender.
        $unreadByConversation = DB::table('msg_messages as m')
            ->join('msg_conversation_participants as p', function ($join) use ($userId) {
                $join->on('p.conversation_id', '=', 'm.conversation_id')
                    ->where('p.user_id', '=', $userId);
            })
            ->whereIn('m.conversation_id', $conversations->pluck('id'))
            ->where('m.sender_id', '!=', $userId)
            ->where(function ($q) {
                $q->whereNull('p.last_read_at')->orWhereColumn('m.created_at', '>', 'p.last_read_at');
            })
            ->groupBy('m.conversation_id')
            ->selectRaw('m.conversation_id as cid, count(*) as unread')
            ->pluck('unread', 'cid');

        $conversations = $conversations->map(function (Conversation $c) use ($unreadByConversation) {
            return [
                'id' => $c->id,
                'type' => $c->type,
                'name' => $c->name,
                'users' => $c->users,
                'last_message' => $c->lastMessage,
                'unread_count' => (int) ($unreadByConversation[$c->id] ?? 0),
            ];
        });

        return response()->json(['data' => $conversations]);
    }

    public function store(Request $request): JsonResponse
    {
        $callerId = $request->user()->id;
        $callerCompanyId = (int) ($request->user()->company_id ?? 0);

        $validated = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            // Chantier 32.28: confirmed empirically that this endpoint had
            // zero tenant scoping — the `users` directory endpoint only
            // narrows the picker's *options*, it never constrained what the
            // API itself would accept, so any authenticated user could POST
            // an arbitrary user id from a completely different company and
            // start a real, persistent, real-time private conversation with
            // them. The company-scoping this closure applies matches the
            // `(int)(... ?? 0)` sentinel convention already established
            // throughout this app for company_id comparisons.
            'user_ids.*' => ['integer', 'exists:users,id', function ($attribute, $value, $fail) use ($callerCompanyId) {
                $recipientCompanyId = (int) (User::find($value)?->company_id ?? 0);
                if ($recipientCompanyId !== $callerCompanyId) {
                    $fail('Tous les destinataires doivent appartenir à la même société.');
                }
            }],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        // Chantier 32.28: excludes the caller's own id from the *recipient*
        // list before deciding direct-vs-group and building the reuse
        // lookup below — previously, POSTing only the caller's own id (e.g.
        // a stale/duplicated id from a buggy client) collapsed to a single
        // participant and threw a real, confirmed "Undefined array key 1"
        // fatal error at $participantIds[1] a few lines down (the direct-
        // reuse query assumes exactly 2 participants). Explicitly rejected
        // instead, with a clear validation message.
        $recipientIds = array_values(array_diff(array_unique($validated['user_ids']), [$callerId]));

        if (empty($recipientIds)) {
            abort(422, 'Vous ne pouvez pas démarrer une conversation avec vous-même.');
        }

        $participantIds = array_merge($recipientIds, [$callerId]);
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
