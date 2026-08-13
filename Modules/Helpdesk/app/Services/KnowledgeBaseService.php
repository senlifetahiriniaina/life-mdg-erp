<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Helpdesk\Models\ArticleView;
use Modules\Helpdesk\Models\KbArticle;
use Modules\Helpdesk\Models\KbCategory;

class KnowledgeBaseService
{
    /**
     * Create category (auto-generate slug from name if not provided).
     */
    public function createCategory(array $data): KbCategory
    {
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        return KbCategory::create($data);
    }

    /**
     * Create article (auto-generate slug from title, compute reading time).
     */
    public function createArticle(array $data): KbArticle
    {
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['title']);
        }

        $article = KbArticle::create($data);

        // Compute and store reading time
        $readingTime = $article->computeReadingTime();
        $article->update(['reading_time_minutes' => $readingTime]);

        return $article->fresh();
    }

    /**
     * Publish article.
     */
    public function publishArticle(KbArticle $article): KbArticle
    {
        $article->publish();

        return $article->fresh();
    }

    /**
     * Search articles by keyword (searches title + content + tags).
     * Returns published articles only, ordered by view_count desc.
     */
    public function search(string $query, int $limit = 20): Collection
    {
        // Strip MySQL fulltext BOOLEAN MODE operators so user input cannot break
        // or manipulate the query (e.g. "+", "-", "*", "(", ")", "~", "<", ">", '"', "@").
        $safe = $this->sanitizeBooleanModeTerm($query);

        return KbArticle::where('status', 'published')
            ->where(function ($q) use ($query, $safe) {
                if (DB::getDriverName() === 'mysql' && $safe !== '') {
                    $q->whereRaw('MATCH(title, content, excerpt) AGAINST(? IN BOOLEAN MODE)', ["{$safe}*"])
                        ->orWhere('title', 'like', "%{$query}%");
                } else {
                    $q->where('title', 'like', "%{$query}%")
                        ->orWhere('content', 'like', "%{$query}%")
                        ->orWhere(function ($qb) use ($query) {
                            $qb->whereNotNull('tags')
                                ->where('tags', 'like', "%{$query}%");
                        });
                }
            })
            ->orderByDesc('view_count')
            ->limit($limit)
            ->get();
    }

    /**
     * Remove MySQL FULLTEXT BOOLEAN MODE operators from a search term, leaving
     * only the searchable text. Prevents query manipulation / syntax errors.
     */
    private function sanitizeBooleanModeTerm(string $query): string
    {
        // Drop boolean operators: + - > < ( ) ~ * " @ and collapse whitespace.
        $cleaned = preg_replace('/[+\-><()~*"@]+/', ' ', $query) ?? '';

        return trim(preg_replace('/\s+/', ' ', $cleaned) ?? '');
    }

    /**
     * Record article view.
     */
    public function recordView(KbArticle $article, ?int $userId = null, string $ip = ''): ArticleView
    {
        $article->incrementView();

        return ArticleView::create([
            'article_id' => $article->id,
            'user_id' => $userId,
            'ip_address' => $ip ?: null,
            'viewed_at' => now(),
            'helpful' => null,
        ]);
    }

    /**
     * Submit feedback (helpful or not).
     */
    public function submitFeedback(KbArticle $article, bool $helpful): void
    {
        if ($helpful) {
            $article->markHelpful();
        } else {
            $article->markNotHelpful();
        }
    }

    /**
     * Get popular articles (by view_count, published only).
     */
    public function getPopularArticles(int $limit = 10): Collection
    {
        return KbArticle::where('status', 'published')
            ->orderByDesc('view_count')
            ->limit($limit)
            ->get();
    }

    /**
     * Get articles by category.
     */
    public function getCategoryArticles(KbCategory $category, bool $publishedOnly = true): Collection
    {
        $query = $category->articles();

        if ($publishedOnly) {
            $query->where('status', 'published');
        }

        return $query->get();
    }

    /**
     * Get KB stats: total_categories, total_articles, published_articles, total_views, avg_helpfulness_rate.
     */
    public function getStats(): array
    {
        $totalCategories = KbCategory::count();
        $totalArticles = KbArticle::count();
        $publishedArticles = KbArticle::where('status', 'published')->count();
        $totalViews = (int) KbArticle::sum('view_count');

        $articles = KbArticle::where('helpful_count', '>', 0)
            ->orWhere('not_helpful_count', '>', 0)
            ->get(['helpful_count', 'not_helpful_count']);

        $avgHelpfulnessRate = 0.0;
        if ($articles->isNotEmpty()) {
            $totalHelpful = $articles->sum('helpful_count');
            $totalNotHelpful = $articles->sum('not_helpful_count');
            $total = $totalHelpful + $totalNotHelpful;
            $avgHelpfulnessRate = $total > 0 ? (float) ($totalHelpful / $total * 100) : 0.0;
        }

        return [
            'total_categories' => $totalCategories,
            'total_articles' => $totalArticles,
            'published_articles' => $publishedArticles,
            'total_views' => $totalViews,
            'avg_helpfulness_rate' => round($avgHelpfulnessRate, 2),
        ];
    }

    /**
     * Get suggested articles for a ticket (simple keyword match on title, by ticket subject string).
     */
    public function getSuggestionsForTicket(string $ticketSubject, int $limit = 5): Collection
    {
        return KbArticle::where('status', 'published')
            ->where('title', 'like', '%'.$ticketSubject.'%')
            ->orderByDesc('helpful_count')
            ->limit($limit)
            ->get();
    }
}
