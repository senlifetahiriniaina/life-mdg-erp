<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Helpdesk\Models\KbArticle;
use Modules\Helpdesk\Models\KbCategory;
use Modules\Helpdesk\Services\KnowledgeBaseService;

/**
 * @group Helpdesk - Knowledge Base
 */
class KnowledgeBaseController extends Controller
{
    public function __construct(
        private readonly KnowledgeBaseService $service
    ) {}

    // ─── Categories ───────────────────────────────────────────────────────────

    public function indexCategories(): JsonResponse
    {
        $categories = KbCategory::with('children')
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->get();

        return response()->json($categories);
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:hd_kb_categories,slug'],
            'description' => ['nullable', 'string'],
            'parent_id' => ['nullable', 'exists:hd_kb_categories,id'],
            'icon' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:7'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['created_by'] = $request->user()->id;
        $category = $this->service->createCategory($validated);

        return response()->json($category, 201);
    }

    public function showCategory(KbCategory $category): JsonResponse
    {
        $category->load('children', 'parent');
        $category->loadCount(['articles as article_count_fresh' => function ($q) {
            $q->where('status', 'published');
        }]);

        return response()->json($category);
    }

    public function updateCategory(Request $request, KbCategory $category): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'parent_id' => ['nullable', 'exists:hd_kb_categories,id'],
            'icon' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:7'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $category->update($validated);

        return response()->json($category);
    }

    public function destroyCategory(KbCategory $category): JsonResponse
    {
        $category->delete();

        return response()->json(null, 204);
    }

    // ─── Articles ─────────────────────────────────────────────────────────────

    public function indexArticles(Request $request): JsonResponse
    {
        $query = KbArticle::with('category:id,name,slug')
            ->when($request->category_id, fn ($q, $v) => $q->where('category_id', (int) $v))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->search, fn ($q, $s) => $q->where(fn ($qb) => $qb->where('title', 'like', "%{$s}%")
                ->orWhere('content', 'like', "%{$s}%")
            ))
            ->latest();

        return response()->json($query->paginate(min((int) ($request->per_page ?? 20), 100)));
    }

    public function storeArticle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => ['required', 'exists:hd_kb_categories,id'],
            'title' => ['required', 'string', 'max:500'],
            'slug' => ['nullable', 'string', 'max:500', 'unique:hd_kb_articles,slug'],
            'content' => ['required', 'string'],
            'excerpt' => ['nullable', 'string'],
            'tags' => ['nullable', 'array'],
            'status' => ['nullable', 'in:draft,published,archived'],
            'author_id' => ['nullable', 'exists:users,id'],
        ]);

        $validated['created_by'] = $request->user()->id;
        if (($validated['status'] ?? 'draft') === 'published') {
            $validated['published_at'] = now();
        }

        $article = $this->service->createArticle($validated);

        return response()->json($article->load('category:id,name'), 201);
    }

    public function showArticle(KbArticle $article): JsonResponse
    {
        $article->incrementView();

        return response()->json($article->load('category:id,name,slug', 'author:id,name'));
    }

    public function updateArticle(Request $request, KbArticle $article): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => ['sometimes', 'exists:hd_kb_categories,id'],
            'title' => ['sometimes', 'string', 'max:500'],
            'content' => ['sometimes', 'string'],
            'excerpt' => ['nullable', 'string'],
            'tags' => ['nullable', 'array'],
            'status' => ['nullable', 'in:draft,published,archived'],
            'author_id' => ['nullable', 'exists:users,id'],
        ]);

        if (isset($validated['status']) && $validated['status'] === 'published' && ! $article->published_at) {
            $validated['published_at'] = now();
        }

        $validated['updated_by'] = $request->user()->id;
        $article->update($validated);

        return response()->json($article);
    }

    public function destroyArticle(KbArticle $article): JsonResponse
    {
        $article->delete();

        return response()->json(null, 204);
    }

    public function publishArticle(KbArticle $article): JsonResponse
    {
        $article = $this->service->publishArticle($article);

        return response()->json($article);
    }

    public function recordView(Request $request, KbArticle $article): JsonResponse
    {
        $view = $this->service->recordView(
            $article,
            $request->user()?->id,
            $request->ip() ?? ''
        );

        return response()->json($view, 201);
    }

    public function submitFeedback(Request $request, KbArticle $article): JsonResponse
    {
        $request->validate(['helpful' => ['required', 'boolean']]);

        $this->service->submitFeedback($article, (bool) $request->helpful);
        $article->refresh();

        return response()->json([
            'helpful_count' => $article->helpful_count,
            'not_helpful_count' => $article->not_helpful_count,
        ]);
    }

    public function popular(): JsonResponse
    {
        $articles = $this->service->getPopularArticles();

        return response()->json($articles);
    }

    public function search(Request $request): JsonResponse
    {
        $request->validate(['q' => ['required', 'string', 'min:2']]);
        $articles = $this->service->search($request->q);

        return response()->json($articles);
    }

    public function stats(): JsonResponse
    {
        return response()->json($this->service->getStats());
    }

    public function suggestions(Request $request): JsonResponse
    {
        $request->validate(['subject' => ['required', 'string', 'min:2']]);
        $articles = $this->service->getSuggestionsForTicket($request->subject);

        return response()->json($articles);
    }
}
