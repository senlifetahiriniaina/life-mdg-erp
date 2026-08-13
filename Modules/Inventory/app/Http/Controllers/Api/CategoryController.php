<?php

namespace Modules\Inventory\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
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
    public function __construct(protected InventoryService $service) {}

    public function index(Request $request)
    {
        $search = $request->query('search');
        $perPage = $request->query('per_page', 15);

        $query = Category::withCount('products');

        if ($search) {
            $query->where('name', 'ilike', "%$search%");
        }

        $categories = $query->paginate($perPage);

        return CategoryResource::collection($categories);
    }

    public function store(StoreCategoryRequest $request)
    {
        $category = $this->service->createCategory($request->validated());

        return (new CategoryResource($category))->response()->setStatusCode(201);
    }

    public function show(Category $category)
    {
        $category->loadCount('products');

        return new CategoryResource($category);
    }

    public function update(UpdateCategoryRequest $request, Category $category)
    {
        $updated = $this->service->updateCategory($category, $request->validated());

        return new CategoryResource($updated);
    }

    public function destroy(Category $category)
    {
        $category->delete();

        return response()->noContent();
    }
}
