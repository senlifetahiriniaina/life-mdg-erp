<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Accounting\Http\Resources\ChartOfAccountResource;
use Modules\Accounting\Models\ChartOfAccount;

/**
 * @group Accounting - ChartOfAccount
 *
 * Manage the chart of accounts (general ledger structure).
 */
class ChartOfAccountController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ChartOfAccount::with('parent', 'children')
            ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('code', 'like', "%{$s}%");
            }))
            ->when($request->type, fn ($q, $v) => $q->where('type', $v))
            ->when($request->parent_id !== null, fn ($q) => $q->where('parent_id', $request->parent_id ?: null))
            ->when($request->is_active !== null, fn ($q) => $q->where('is_active', $request->boolean('is_active')));

        $perPage = min((int) ($request->per_page ?? 25), 100);

        return response()->json(
            ChartOfAccountResource::collection($query->orderBy('code')->paginate($perPage))->response()->getData(true)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:acc_chart_of_accounts,code'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:asset,liability,equity,revenue,expense'],
            'description' => ['nullable', 'string'],
            'parent_id' => ['nullable', 'exists:acc_chart_of_accounts,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $account = ChartOfAccount::create($validated);

        return response()->json(
            new ChartOfAccountResource($account->load('parent', 'children')),
            201
        );
    }

    public function show(ChartOfAccount $chartOfAccount): JsonResponse
    {
        return response()->json(
            new ChartOfAccountResource($chartOfAccount->load('parent', 'children'))
        );
    }

    public function update(Request $request, ChartOfAccount $chartOfAccount): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['sometimes', 'string', 'max:50', "unique:acc_chart_of_accounts,code,{$chartOfAccount->id}"],
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', 'in:asset,liability,equity,revenue,expense'],
            'description' => ['nullable', 'string'],
            'parent_id' => ['nullable', 'exists:acc_chart_of_accounts,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $chartOfAccount->update($validated);

        return response()->json(
            new ChartOfAccountResource($chartOfAccount->fresh('parent', 'children'))
        );
    }

    public function destroy(ChartOfAccount $chartOfAccount): JsonResponse
    {
        if ($chartOfAccount->children()->exists()) {
            return response()->json(['message' => 'Cannot delete an account that has sub-accounts.'], 422);
        }

        $chartOfAccount->delete();

        return response()->json(null, 204);
    }
}
