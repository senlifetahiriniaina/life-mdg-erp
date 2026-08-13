<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Services;

use Modules\Helpdesk\Models\KbArticle;

class AnswerBotService
{
    /**
     * Find relevant KB articles for a question.
     *
     * @return array<string, mixed>
     */
    public function findAnswer(string $question): array
    {
        $articles = KbArticle::where('status', 'published')
            ->where(function ($q) use ($question) {
                $q->where('title', 'LIKE', '%'.$question.'%')
                    ->orWhere('content', 'LIKE', '%'.substr($question, 0, 30).'%');
            })
            ->limit(3)
            ->get(['id', 'title', 'excerpt', 'slug']);

        return [
            'articles' => $articles,
            'confidence' => $articles->isNotEmpty() ? 0.82 : 0.0,
        ];
    }
}
