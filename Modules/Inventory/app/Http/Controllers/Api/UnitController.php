<?php

declare(strict_types=1);

namespace Modules\Inventory\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Inventory\Http\Controllers\Api\Concerns\ScopesToCompany;
use Modules\Inventory\Http\Resources\UnitResource;
use Modules\Inventory\Models\Unit;

/**
 * @group Inventory - Unit
 *
 * Manage units of measure.
 *
 * Chantier 32: had zero company/tenant scoping of any kind — fixed via
 * ScopesToCompany, same proportionality precedent as CategoryController
 * (no Policy class introduced, since no authorize() call existed here
 * before this either).
 */
class UnitController extends Controller
{
    use ScopesToCompany;

    public function index(Request $request): JsonResponse
    {
        $query = Unit::query()
            ->where('company_id', $this->companyId($request))
            ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('symbol', 'like', "%{$s}%");
            }))
            ->when($request->type, fn ($q, $v) => $q->where('type', $v));

        $perPage = min((int) ($request->per_page ?? 25), 100);

        return response()->json(
            UnitResource::collection($query->orderBy('name')->paginate($perPage))->response()->getData(true)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'symbol' => ['required', 'string', 'max:20'],
            'type' => ['nullable', 'string', 'max:50'],
        ]);

        $unit = Unit::create($validated + ['company_id' => $this->companyId($request)]);

        return response()->json(new UnitResource($unit), 201);
    }

    public function show(Request $request, Unit $unit): JsonResponse
    {
        $this->assertSameCompany($request, $unit);

        return response()->json(new UnitResource($unit));
    }

    public function update(Request $request, Unit $unit): JsonResponse
    {
        $this->assertSameCompany($request, $unit);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'symbol' => ['sometimes', 'string', 'max:20'],
            'type' => ['nullable', 'string', 'max:50'],
        ]);

        $unit->update($validated);

        return response()->json(new UnitResource($unit->fresh()));
    }

    public function destroy(Request $request, Unit $unit): JsonResponse
    {
        $this->assertSameCompany($request, $unit);

        if ($unit->products()->exists()) {
            return response()->json(['message' => 'Cannot delete a unit of measure that is in use.'], 422);
        }

        $unit->delete();

        return response()->json(null, 204);
    }
}
