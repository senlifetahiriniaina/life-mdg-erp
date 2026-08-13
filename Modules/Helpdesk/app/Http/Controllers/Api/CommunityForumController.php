<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Helpdesk\Models\Forum;
use Modules\Helpdesk\Models\ForumReply;
use Modules\Helpdesk\Models\ForumThread;
use Modules\Helpdesk\Services\ForumService;

/**
 * @group Helpdesk - Community Forums
 *
 * Forum boards, threads, replies, votes and answer acceptance.
 */
class CommunityForumController extends Controller
{
    public function __construct(private readonly ForumService $service) {}

    // ── Forums ────────────────────────────────────────────────────────────

    /** List all forums with thread counts. */
    public function indexForums(Request $request): JsonResponse
    {
        $forums = Forum::withCount('threads')
            ->when(
                ! $request->boolean('show_private'),
                fn ($q) => $q->where('is_public', true)
            )
            ->orderBy('sort_order')
            ->get();

        return response()->json($forums);
    }

    /** Create a new forum board (admin/manager only). */
    public function storeForumBoard(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'category'    => 'nullable|string|max:100',
            'is_public'   => 'boolean',
            'sort_order'  => 'integer|min:0',
        ]);

        $forum = Forum::create($data);

        return response()->json($forum, 201);
    }

    /** Show a single forum with its thread list. */
    public function showForum(Forum $forum): JsonResponse
    {
        $forum->load(['threads' => function ($q) {
            $q->withCount('replies')
                ->with('author:id,name')
                ->orderByRaw("FIELD(status,'pinned','open','closed')")
                ->latest()
                ->paginate(20);
        }]);

        return response()->json($forum);
    }

    /** List threads for a forum (paginated). */
    public function indexThreads(Request $request, Forum $forum): JsonResponse
    {
        $threads = $forum->threads()
            ->withCount('replies')
            ->with('author:id,name')
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->orderByRaw("FIELD(status,'pinned','open','closed')")
            ->latest()
            ->paginate(20);

        return response()->json($threads);
    }

    // ── Threads ───────────────────────────────────────────────────────────

    /** Create a thread in a forum. */
    public function storeThread(Request $request, Forum $forum): JsonResponse
    {
        $data = $request->validate([
            'title'   => 'required|string|max:255',
            'content' => 'required|string',
        ]);

        $thread = $this->service->createThread($forum, $request->user()->id, $data);
        $thread->load('author:id,name');

        return response()->json($thread, 201);
    }

    /** Show a thread with all its replies. */
    public function showThread(ForumThread $thread): JsonResponse
    {
        $thread->increment('views');
        $thread->load([
            'author:id,name',
            'forum:id,name,slug',
            'replies' => fn ($q) => $q->with('author:id,name')->orderBy('is_accepted_answer', 'desc')->latest(),
        ]);

        return response()->json($thread);
    }

    /** Close a thread (moderator/admin). */
    public function closeThread(ForumThread $thread): JsonResponse
    {
        $closed = $this->service->markThreadClosed($thread);

        return response()->json($closed);
    }

    /** Vote on a thread. direction: 1 (up) or -1 (down). */
    public function voteThread(Request $request, ForumThread $thread): JsonResponse
    {
        $data = $request->validate(['direction' => 'required|integer|in:1,-1']);
        $result = $this->service->voteOnThread($thread, $request->user()->id, $data['direction']);

        return response()->json($result);
    }

    // ── Replies ───────────────────────────────────────────────────────────

    /** Post a reply to a thread. */
    public function storeReply(Request $request, ForumThread $thread): JsonResponse
    {
        abort_if($thread->status === 'closed', 422, 'Thread is closed.');

        $data = $request->validate(['content' => 'required|string']);
        $reply = $this->service->replyToThread($thread, $request->user()->id, $data);
        $reply->load('author:id,name');

        return response()->json($reply, 201);
    }

    /** Mark a reply as the accepted answer (thread author or moderator). */
    public function acceptAnswer(Request $request, ForumThread $thread, ForumReply $reply): JsonResponse
    {
        if ($thread->author_id !== $request->user()->id
            && ! $request->user()->hasAnyRole(['admin', 'super-admin', 'manager'])) {
            abort(403, 'Only the thread author or a moderator can accept an answer.');
        }

        abort_if($reply->thread_id !== $thread->id, 422, 'Reply does not belong to this thread.');

        $accepted = $this->service->acceptAnswer($thread, $reply);

        return response()->json([
            'reply'        => $accepted,
            'is_answered'  => true,
        ]);
    }

    /** Vote on a reply. direction: 1 or -1. */
    public function voteReply(Request $request, ForumReply $reply): JsonResponse
    {
        $data = $request->validate(['direction' => 'required|integer|in:1,-1']);
        $result = $this->service->voteOnReply($reply, $request->user()->id, $data['direction']);

        return response()->json($result);
    }

    // ── Search ────────────────────────────────────────────────────────────

    /** Full-text search across all threads. */
    public function search(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => 'required|string|min:2|max:255']);
        $results = $this->service->searchForum($data['q']);

        return response()->json($results);
    }

    /** Popular/trending threads. */
    public function popular(): JsonResponse
    {
        $threads = $this->service->getPopularThreads();

        return response()->json($threads);
    }
}
