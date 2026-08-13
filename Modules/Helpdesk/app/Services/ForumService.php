<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Helpdesk\Models\Forum;
use Modules\Helpdesk\Models\ForumReply;
use Modules\Helpdesk\Models\ForumThread;
use Modules\Helpdesk\Models\ForumVote;

class ForumService
{
    /**
     * Create a new thread inside a forum.
     *
     * @param  array{title: string, content: string}  $data
     */
    public function createThread(Forum $forum, int $authorId, array $data): ForumThread
    {
        return $forum->threads()->create([
            'author_id' => $authorId,
            'title'     => $data['title'],
            'content'   => $data['content'],
            'status'    => 'open',
        ]);
    }

    /**
     * Add a reply to a thread.
     *
     * @param  array{content: string}  $data
     */
    public function replyToThread(ForumThread $thread, int $authorId, array $data): ForumReply
    {
        return ForumReply::create([
            'thread_id' => $thread->id,
            'author_id' => $authorId,
            'content'   => $data['content'],
        ]);
    }

    /**
     * Mark a reply as the accepted answer and flag the thread as answered.
     */
    public function acceptAnswer(ForumThread $thread, ForumReply $reply): ForumReply
    {
        // Unmark any previous accepted answer in the thread
        ForumReply::where('thread_id', $thread->id)
            ->where('is_accepted_answer', true)
            ->update(['is_accepted_answer' => false]);

        $reply->update(['is_accepted_answer' => true]);
        $thread->update(['is_answered' => true]);

        return $reply->fresh();
    }

    /**
     * Cast or change a vote on a votable entity (thread or reply).
     * If the user already voted with the same direction, the vote is removed (toggle).
     *
     * @param  ForumThread|ForumReply  $votable
     */
    public function vote(ForumThread|ForumReply $votable, int $userId, int $direction): array
    {
        $existing = ForumVote::where([
            'votable_type' => get_class($votable),
            'votable_id'   => $votable->id,
            'user_id'      => $userId,
        ])->first();

        if ($existing) {
            if ($existing->vote === $direction) {
                // Toggle off
                $existing->delete();
                $votable->decrement('upvotes', $direction > 0 ? 1 : 0);

                return ['action' => 'removed', 'upvotes' => $votable->fresh()->upvotes];
            }
            // Change direction
            $existing->update(['vote' => $direction]);
            $votable->increment('upvotes', $direction > 0 ? 2 : -2); // swing of 2
        } else {
            ForumVote::create([
                'votable_type' => get_class($votable),
                'votable_id'   => $votable->id,
                'user_id'      => $userId,
                'vote'         => $direction,
            ]);
            $votable->increment('upvotes', $direction > 0 ? 1 : -1);
        }

        return ['action' => 'recorded', 'upvotes' => $votable->fresh()->upvotes];
    }

    /**
     * Vote on a thread (convenience wrapper).
     */
    public function voteOnThread(ForumThread $thread, int $userId, int $direction): array
    {
        return $this->vote($thread, $userId, $direction);
    }

    /**
     * Vote on a reply (convenience wrapper).
     */
    public function voteOnReply(ForumReply $reply, int $userId, int $direction): array
    {
        return $this->vote($reply, $userId, $direction);
    }

    /**
     * Return the most popular threads (sorted by upvotes + reply count).
     */
    public function getPopularThreads(int $limit = 10): LengthAwarePaginator
    {
        return ForumThread::withCount('replies')
            ->with(['author:id,name', 'forum:id,name,slug'])
            ->orderByDesc('upvotes')
            ->orderByDesc('replies_count')
            ->paginate($limit);
    }

    /**
     * Full-text search across thread titles and content.
     */
    public function searchForum(string $query, int $perPage = 20): LengthAwarePaginator
    {
        return ForumThread::withCount('replies')
            ->with(['author:id,name', 'forum:id,name,slug'])
            ->where(function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                    ->orWhere('content', 'like', "%{$query}%");
            })
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Close a thread (prevents new replies).
     */
    public function markThreadClosed(ForumThread $thread): ForumThread
    {
        $thread->update(['status' => 'closed']);

        return $thread->fresh();
    }
}
