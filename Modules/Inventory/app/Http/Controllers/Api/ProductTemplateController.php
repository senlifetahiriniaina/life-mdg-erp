<?php

declare(strict_types=1);

namespace Modules\Inventory\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Inventory\Http\Requests\StoreProductTemplateRequest;
use Modules\Inventory\Http\Requests\UpdateProductTemplateRequest;
use Modules\Inventory\Models\ProductTemplate;

/**
 * Chantier 17 — catalogue of clothing-domain product templates, editable by
 * admin/manager/inventory staff (Chantier 17b: full CRUD, not just the
 * seeded defaults — the user explicitly asked for editable/addable/
 * removable templates). Picking one in the product-creation UI pre-fills
 * category/unit/attributes; each template's Category carries the suggested
 * chart-of-accounts routing (see CategoryResource/Category::default_*_account_code).
 */
class ProductTemplateController extends Controller
{
    private const WITH = ['category:id,name,default_stock_account_code,default_purchase_account_code,default_sale_account_code,default_variance_account_code', 'unit:id,name,symbol'];

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ProductTemplate::class);

        $templates = ProductTemplate::with(self::WITH)
            // The product-creation picker only ever wants active templates;
            // the management page passes include_inactive=1 to also see/edit
            // ones a user has deactivated rather than deleted.
            ->when(!$request->boolean('include_inactive'), fn ($q) => $q->active())
            ->when($request->filled('family'), fn ($q) => $q->family($request->string('family')))
            ->orderBy('family')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $templates,
            'families' => ProductTemplate::FAMILIES,
        ]);
    }

    public function store(StoreProductTemplateRequest $request): JsonResponse
    {
        $this->authorize('create', ProductTemplate::class);

        $template = ProductTemplate::create($request->validated() + ['is_active' => $request->boolean('is_active', true)]);
        $template->load(self::WITH);

        return response()->json(['data' => $template], 201);
    }

    public function update(UpdateProductTemplateRequest $request, ProductTemplate $productTemplate): JsonResponse
    {
        $this->authorize('update', $productTemplate);

        $productTemplate->update($request->validated() + ['is_active' => $request->boolean('is_active', $productTemplate->is_active)]);
        $productTemplate->load(self::WITH);

        return response()->json(['data' => $productTemplate]);
    }

    public function destroy(ProductTemplate $productTemplate): JsonResponse
    {
        $this->authorize('delete', $productTemplate);

        $productTemplate->delete();

        return response()->json(null, 204);
    }
}
