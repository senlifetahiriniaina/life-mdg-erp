<?php

namespace Modules\Inventory\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Inertia\Inertia;

/**
 * Chantier 8.3: create/show/edit rendered pages (Form.vue/Show.vue) that never
 * existed anywhere in the repo — 500 on every visit. Categories/Index.vue is a
 * self-contained list+modal-CRUD page (same pattern as the real, working
 * Warehouses/Index.vue) that talks directly to the categories API, so the
 * separate create/store/show/edit/update/destroy web actions were dead weight
 * on top of being broken; trimmed to the one real route.
 */
class CategoryController extends Controller
{
    public function index()
    {
        return Inertia::render('Inventory/Categories/Index');
    }
}
