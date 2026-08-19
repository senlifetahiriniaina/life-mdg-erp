<?php

declare(strict_types=1);

namespace Modules\Inventory\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Inventory\Models\ProductTemplate;

/**
 * Chantier 17 — read-mostly catalogue of clothing-domain product templates.
 * Picking one in the product-creation UI pre-fills category/unit/attributes;
 * each template's Category carries the suggested chart-of-accounts routing
 * (see CategoryResource/Category::default_*_account_code).
 */
class ProductTemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ProductTemplate::class);

        $templates = ProductTemplate::with('category:id,name,default_stock_account_code,default_purchase_account_code,default_sale_account_code,default_variance_account_code', 'unit:id,name,symbol')
            ->active()
            ->when($request->filled('family'), fn ($q) => $q->family($request->string('family')))
            ->orderBy('family')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $templates,
            'families' => ProductTemplate::FAMILIES,
        ]);
    }
}
