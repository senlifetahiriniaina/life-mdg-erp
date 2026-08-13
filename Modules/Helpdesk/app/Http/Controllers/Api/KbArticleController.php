<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Helpdesk\Models\KbArticle;

/**
 * @group Helpdesk - Knowledge Base Articles
 */
class KbArticleController extends Controller
{
    /**
     * List articles with optional full-text search and category filter.
     *
     * @queryParam search string Full-text search on title and content. Example: how to reset password
     * @queryParam category_id integer Filter by category. Example: 2
     * @queryParam status string Filter by status (published, draft, archived). Example: published
     * @queryParam per_page integer Results per page. Example: 20
     */
    public function index(Request $request): JsonResponse
    {
        $query = KbArticle::with('category:id,name,slug', 'createdBy:id,name')
            ->when($request->category_id, fn ($q, $v) => $q->where('category_id', $v))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->search, function ($q, $s) {
                if (DB::getDriverName() === 'mysql') {
                    $q->whereRaw('MATCH(title, content, excerpt) AGAINST(? IN BOOLEAN MODE)', ["{$s}*"]);
                } else {
                    $q->where(fn ($qb) => $qb->where('title', 'like', "%{$s}%")
                        ->orWhere('content', 'like', "%{$s}%")
                        ->orWhere('excerpt', 'like', "%{$s}%"));
                }
            })
            ->latest();

        return response()->json($query->paginate(min((int) ($request->per_page ?? 20), 100)));
    }

    /**
     * Suggest articles relevant to a ticket subject (used at ticket creation).
     *
     * @queryParam q string Ticket subject or description. Example: cannot login
     */
    public function suggest(Request $request): JsonResponse
    {
        $request->validate(['q' => ['required', 'string', 'min:3']]);
        $q = $request->q;

        $articles = KbArticle::where('status', 'published')
            ->where(function ($query) use ($q) {
                if (DB::getDriverName() === 'mysql') {
                    $query->whereRaw('MATCH(title, content, excerpt) AGAINST(? IN BOOLEAN MODE)', ["{$q}*"])
                        ->orWhere('title', 'like', "%{$q}%");
                } else {
                    $query->where('title', 'like', "%{$q}%")
                        ->orWhere('content', 'like', "%{$q}%")
                        ->orWhere('excerpt', 'like', "%{$q}%");
                }
            })
            ->select('id', 'title', 'slug', 'excerpt', 'category_id', 'helpful_count')
            ->with('category:id,name')
            ->orderByDesc('helpful_count')
            ->limit(5)
            ->get();

        return response()->json($articles);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => ['required', 'exists:hd_kb_categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'tags' => ['nullable', 'array'],
            'status' => ['nullable', 'in:draft,published,archived'],
        ]);

        $article = KbArticle::create(array_merge($validated, [
            'slug' => Str::slug($validated['title']),
            'created_by' => $request->user()->id,
            'published_at' => ($validated['status'] ?? 'draft') === 'published' ? now() : null,
        ]));

        return response()->json($article->load('category:id,name'), 201);
    }

    public function show(KbArticle $kbArticle): JsonResponse
    {
        // Increment view count
        $kbArticle->increment('view_count');

        return response()->json($kbArticle->load('category:id,name,slug', 'createdBy:id,name'));
    }

    public function update(Request $request, KbArticle $kbArticle): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => ['sometimes', 'exists:hd_kb_categories,id'],
            'title' => ['sometimes', 'string', 'max:255'],
            'content' => ['sometimes', 'string'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'tags' => ['nullable', 'array'],
            'status' => ['nullable', 'in:draft,published,archived'],
        ]);

        if (isset($validated['title'])) {
            $validated['slug'] = Str::slug($validated['title']);
        }

        if (isset($validated['status']) && $validated['status'] === 'published' && ! $kbArticle->published_at) {
            $validated['published_at'] = now();
        }

        $validated['updated_by'] = $request->user()->id;
        $kbArticle->update($validated);

        return response()->json($kbArticle);
    }

    public function destroy(KbArticle $kbArticle): JsonResponse
    {
        $kbArticle->delete();

        return response()->json(null, 204);
    }

    /**
     * Mark article as helpful or not helpful.
     */
    public function feedback(Request $request, KbArticle $kbArticle): JsonResponse
    {
        $request->validate(['helpful' => ['required', 'boolean']]);

        if ($request->helpful) {
            $kbArticle->increment('helpful_count');
        } else {
            $kbArticle->increment('not_helpful_count');
        }

        return response()->json([
            'helpful_count' => $kbArticle->helpful_count,
            'not_helpful_count' => $kbArticle->not_helpful_count,
        ]);
    }
}
