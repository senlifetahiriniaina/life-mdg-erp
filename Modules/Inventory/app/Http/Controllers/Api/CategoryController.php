<?php

namespace Modules\Inventory\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Inventory\Http\Controllers\Api\Concerns\ScopesToCompany;
use Modules\Inventory\Http\Requests\StoreCategoryRequest;
use Modules\Inventory\Http\Requests\UpdateCategoryRequest;
use Modules\Inventory\Http\Resources\CategoryResource;
use Modules\Inventory\Models\Category;
use Modules\Inventory\Services\InventoryService;

/**
 * @group Controllers - Category
 *
 * Manage Category resources.
 */
class CategoryController extends Controller
{
    use ScopesToCompany;

    public function __construct(protected InventoryService $service) {}

    public function index(Request $request)
    {
        $search = $request->query('search');
        $perPage = $request->query('per_page', 15);

        $query = $this->scopeToCompany(Category::withCount('products'), $request);

        if ($search) {
            // Chantier 32.22: was 'ilike' — a Postgres-only operator this app
            // never uses (MySQL/SQLite only) — a guaranteed 500 on every
            // real search request, never caught because no test exercised
            // the `search` query param at all.
            $query->where('name', 'LIKE', "%$search%");
        }

        $categories = $query->paginate($perPage);

        return CategoryResource::collection($categories);
    }

    public function store(StoreCategoryRequest $request)
    {
        $data = $request->validated();
        $data['company_id'] = $this->companyId($request);

        $category = $this->service->createCategory($data);

        return (new CategoryResource($category))->response()->setStatusCode(201);
    }

    public function show(Request $request, Category $category)
    {
        $this->assertSameCompany($request, $category);

        $category->loadCount('products');

        return new CategoryResource($category);
    }

    public function update(UpdateCategoryRequest $request, Category $category)
    {
        $this->assertSameCompany($request, $category);

        $updated = $this->service->updateCategory($category, $request->validated());

        return new CategoryResource($updated);
    }

    public function destroy(Request $request, Category $category)
    {
        $this->assertSameCompany($request, $category);

        $category->delete();

        return response()->noContent();
    }
}
