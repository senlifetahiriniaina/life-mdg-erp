<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Helpdesk\Models\KbPortalArticle;
use Modules\Helpdesk\Models\KbPortalCategory;

class PortalWebController extends Controller
{
    public function index(): Response
    {
        // KnowledgeBaseService::getPublicCategories() never existed on that
        // class (confirmed via get_class_methods) — a guaranteed fatal
        // BadMethodCallException on every real visit to /helpdesk/portal.
        // It also operates on the wrong model pair anyway
        // (KbCategory/KbArticle, the "unified" KnowledgeBaseController's
        // own models) — Portal/Index.vue's categories/featuredArticles
        // props are shaped for KbPortalCategory/KbPortalArticle, the same
        // pair KbPortalController already serves this page's own fetch()
        // calls against.
        $categories = KbPortalCategory::where('is_public', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'description', 'slug']);

        $featuredArticles = KbPortalArticle::where('status', 'published')
            ->orderByDesc('view_count')
            ->limit(6)
            ->get(['id', 'title', 'slug', 'excerpt', 'category_id', 'view_count']);

        return Inertia::render('Helpdesk/Portal/Index', [
            'categories' => $categories,
            'featuredArticles' => $featuredArticles,
        ]);
    }
}
