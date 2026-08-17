<?php

namespace Modules\Inventory\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Inertia\Inertia;

/**
 * Chantier 8.3: create/show/edit rendered pages (Form.vue/Show.vue) that never
 * existed anywhere in the repo — 500 on every visit. Warehouses/Index.vue is
 * already a self-contained list+modal-CRUD page talking directly to the
 * warehouses API, so the separate create/store/show/edit/update/destroy web
 * actions were dead weight on top of being broken; trimmed to the one real
 * route (same fix applied to CategoryController).
 */
class WarehouseController extends Controller
{
    public function index()
    {
        return Inertia::render('Inventory/Warehouses/Index');
    }
}
