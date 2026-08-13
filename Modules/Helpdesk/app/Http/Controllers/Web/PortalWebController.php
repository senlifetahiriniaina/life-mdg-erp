<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Helpdesk\Models\KbPortalArticle;
use Modules\Helpdesk\Services\KnowledgeBaseService;

class PortalWebController extends Controller
{
    public function __construct(private readonly KnowledgeBaseService $kbService) {}

    public function index(): Response
    {
        $categories = $this->kbService->getPublicCategories();

        $featuredArticles = KbPortalArticle::where('status', 'published')
            ->orderByDesc('views_count')
            ->limit(6)
            ->get(['id', 'title', 'slug', 'excerpt', 'category_id', 'views_count']);

        return Inertia::render('Helpdesk/Portal/Index', [
            'categories' => $categories,
            'featuredArticles' => $featuredArticles,
        ]);
    }
}
