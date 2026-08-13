<?php

declare(strict_types=1);

namespace Modules\CRM\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\CRM\Models\Account;

/**
 * @group CRM - Account
 *
 * Manage CRM accounts (companies).
 */
class AccountController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $allowedSorts = ['name', 'annual_revenue', 'created_at', 'updated_at'];
        $sortParam = $request->sort ?? '-created_at';
        $sortDir = str_starts_with($sortParam, '-') ? 'desc' : 'asc';
        $sortCol = ltrim($sortParam, '-');
        if (!in_array($sortCol, $allowedSorts)) {
            $sortCol = 'created_at';
        }

        $query = Account::with('owner')
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%")
                ->orWhere('email', 'like', "%{$s}%"))
            ->when($request->type, fn ($q, $v) => $q->where('type', $v))
            ->when($request->industry, fn ($q, $v) => $q->where('industry', $v))
            ->when($request->status, fn ($q, $v) => $q->whereIn('status', array_map('trim', explode(',', (string) $v))))
            ->when($request->owner_id, fn ($q, $v) => $q->where('owner_id', $v))
            ->orderBy($sortCol, $sortDir);

        return response()->json($query->paginate(min((int) ($request->per_page ?? 25), 100)));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'in:prospect,customer,partner,vendor'],
            'industry' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'url'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email'],
            'employee_count' => ['nullable', 'integer', 'min:1'],
            'annual_revenue' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'billing_address' => ['nullable', 'string'],
            'billing_city' => ['nullable', 'string'],
            'billing_country' => ['nullable', 'string', 'size:2'],
            'description' => ['nullable', 'string'],
            'custom_fields' => ['nullable', 'array'],
        ]);

        $account = Account::create(array_merge($validated, ['owner_id' => $request->user()->id]));

        return response()->json($account->load('owner'), 201);
    }

    public function show(Account $account): JsonResponse
    {
        $this->authorize('view', $account);

        return response()->json($account->load('owner', 'contacts'));
    }

    public function update(Request $request, Account $account): JsonResponse
    {
        $this->authorize('update', $account);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['nullable', 'in:prospect,customer,partner,vendor'],
            'industry' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'url'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email'],
            'employee_count' => ['nullable', 'integer', 'min:1'],
            'annual_revenue' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'billing_address' => ['nullable', 'string'],
            'billing_city' => ['nullable', 'string'],
            'billing_country' => ['nullable', 'string', 'size:2'],
            'description' => ['nullable', 'string'],
            'custom_fields' => ['nullable', 'array'],
        ]);

        $account->update($validated);

        return response()->json($account->fresh('owner'));
    }

    public function destroy(Account $account): JsonResponse
    {
        $this->authorize('delete', $account);

        // Nullify account_id on related contacts before deleting
        $account->contacts()->update(['account_id' => null]);
        $account->delete();

        return response()->json(null, 204);
    }
}
