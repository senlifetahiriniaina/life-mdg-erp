<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Helpdesk\Models\ForumPost;
use Modules\Helpdesk\Models\ForumReply;

/**
 * @group Controllers - Forum
 *
 * Manage Forum resources.
 */
class ForumController extends Controller
{
    // Posts

    public function index(Request $request): JsonResponse
    {
        $posts = ForumPost::withCount('replies')
            ->with('author:id,name')
            ->when($request->category, fn ($q, $v) => $q->where('category', $v))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->latest()
            ->paginate(20);

        return response()->json($posts);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'category' => 'nullable|string|max:100',
        ]);

        $data['author_id'] = $request->user()->id;
        $data['status'] = 'open';

        $post = ForumPost::create($data);

        return response()->json($post->load('author:id,name'), 201);
    }

    public function show(ForumPost $forumPost): JsonResponse
    {
        $forumPost->increment('views');
        $forumPost->load([
            'author:id,name',
            'replies.author:id,name',
        ]);

        return response()->json($forumPost);
    }

    public function update(Request $request, ForumPost $forumPost): JsonResponse
    {
        if ($forumPost->author_id !== $request->user()->id
            && ! $request->user()->hasAnyRole(['super-admin', 'admin', 'manager'])) {
            abort(403, 'You can only edit your own posts.');
        }

        $data = $request->validate([
            'title' => 'sometimes|string|max:255',
            'content' => 'sometimes|string',
            'category' => 'nullable|string|max:100',
            'status' => 'sometimes|in:open,answered,closed',
        ]);

        $forumPost->update($data);

        return response()->json($forumPost->fresh());
    }

    public function destroy(Request $request, ForumPost $forumPost): JsonResponse
    {
        if ($forumPost->author_id !== $request->user()->id
            && ! $request->user()->hasAnyRole(['super-admin', 'admin', 'manager'])) {
            abort(403, 'You can only delete your own posts.');
        }

        $forumPost->delete();

        return response()->json(['message' => 'Post deleted.']);
    }

    // Replies

    public function storeReply(Request $request, ForumPost $forumPost): JsonResponse
    {
        $data = $request->validate(['content' => 'required|string']);

        $reply = $forumPost->replies()->create([
            'content' => $data['content'],
            'author_id' => $request->user()->id,
        ]);

        return response()->json($reply->load('author:id,name'), 201);
    }

    public function destroyReply(Request $request, ForumPost $forumPost, ForumReply $forumReply): JsonResponse
    {
        if ($forumReply->author_id !== $request->user()->id
            && ! $request->user()->hasAnyRole(['super-admin', 'admin', 'manager'])) {
            abort(403, 'You can only delete your own replies.');
        }

        $forumReply->delete();

        return response()->json(['message' => 'Reply deleted.']);
    }

    public function acceptAnswer(ForumPost $forumPost, ForumReply $forumReply): JsonResponse
    {
        // Reset previous accepted
        $forumPost->replies()->update(['is_accepted' => false]);

        $forumReply->update(['is_accepted' => true]);
        $forumPost->update([
            'accepted_answer_id' => $forumReply->id,
            'status' => 'answered',
        ]);

        return response()->json(['message' => 'Answer accepted.', 'post_status' => 'answered']);
    }

    public function votePost(Request $request, ForumPost $forumPost): JsonResponse
    {
        $data = $request->validate(['direction' => 'required|in:up,down']);
        $forumPost->increment('votes', $data['direction'] === 'up' ? 1 : -1);

        return response()->json(['votes' => $forumPost->fresh()->votes]);
    }

    public function voteReply(Request $request, ForumPost $forumPost, ForumReply $forumReply): JsonResponse
    {
        $data = $request->validate(['direction' => 'required|in:up,down']);
        $forumReply->increment('votes', $data['direction'] === 'up' ? 1 : -1);

        return response()->json(['votes' => $forumReply->fresh()->votes]);
    }
}
