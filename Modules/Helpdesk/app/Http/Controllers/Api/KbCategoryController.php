<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Helpdesk\Models\KbCategory;

/**
 * @group Helpdesk - Knowledge Base Categories
 */
class KbCategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $categories = KbCategory::with('children')
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->paginate($request->integer('per_page', 50));

        return response()->json($categories);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'exists:hd_kb_categories,id'],
            'description' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $category = KbCategory::create(array_merge($validated, [
            'slug' => Str::slug($validated['name']),
            'created_by' => $request->user()->id,
        ]));

        return response()->json($category, 201);
    }

    public function show(KbCategory $kbCategory): JsonResponse
    {
        return response()->json($kbCategory->load('children', 'articles'));
    }

    public function update(Request $request, KbCategory $kbCategory): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'parent_id' => ['nullable', 'exists:hd_kb_categories,id'],
            'description' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if (isset($validated['name'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $kbCategory->update($validated);

        return response()->json($kbCategory);
    }

    public function destroy(KbCategory $kbCategory): JsonResponse
    {
        $kbCategory->delete();

        return response()->json(null, 204);
    }
}
