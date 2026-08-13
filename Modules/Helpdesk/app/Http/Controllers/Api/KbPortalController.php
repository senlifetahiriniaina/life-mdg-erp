<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\Helpdesk\Models\KbPortalArticle;

/**
 * @group Controllers - Kb Portal
 *
 * Manage Kb Portal resources.
 */
class KbPortalController extends Controller
{
    /**
     * List published articles (public).
     */
    public function index(Request $request): JsonResponse
    {
        $query = KbPortalArticle::where('status', 'published')
            ->with('category');

        if ($request->filled('search')) {
            $term = '%'.$request->string('search').'%';
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', $term)
                    ->orWhere('content', 'like', $term);
            });
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        $articles = $query->paginate(20);

        return response()->json($articles);
    }

    /**
     * Create a new portal article (auth required).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => 'required|integer|exists:hd_kb_portal_categories,id',
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'status' => 'nullable|string|in:draft,published,archived',
        ]);

        $article = KbPortalArticle::create([
            ...$validated,
            'author_id' => $request->user()?->id,
            'status' => $validated['status'] ?? 'draft',
        ]);

        return response()->json($article, 201);
    }

    /**
     * Show a single article.
     */
    public function show(KbPortalArticle $kbPortalArticle): JsonResponse
    {
        $kbPortalArticle->increment('view_count');

        return response()->json($kbPortalArticle->load('category'));
    }

    /**
     * Delete an article.
     */
    public function destroy(KbPortalArticle $kbPortalArticle): Response
    {
        $kbPortalArticle->delete();

        return response()->noContent();
    }
}
