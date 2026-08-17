<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Helpdesk\Models\KbArticle;

/**
 * @group Helpdesk - Knowledge Base Articles
 *
 * index/store/show/update/destroy/feedback were dropped in Chantier 8.2: Laravel's
 * RouteCollection keys routes by method+URI (last registration wins on a collision),
 * and KnowledgeBaseController registers the same URIs later in routes/api.php, so
 * those methods here were dead code, never actually dispatched to. suggest() is the
 * only method on a URI not also claimed by KnowledgeBaseController.
 */
class KbArticleController extends Controller
{
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
}
