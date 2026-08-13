<?php

declare(strict_types=1);

namespace Modules\Logistics\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @group Logistics - Locations
 *
 * Manages WMS locations (zones, aisles, racks, bins) within warehouses.
 */
class LocationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = DB::table('logistics_locations')
            ->when($request->input('type'), fn ($q, $v) => $q->where('type', $v))
            ->when($request->input('location_class'), fn ($q, $v) => $q->where('location_class', $v))
            ->when($request->input('parent_id'), fn ($q, $v) => $q->where('parent_id', $v))
            ->when($request->input('warehouse_id'), fn ($q, $v) => $q->where('warehouse_id', $v))
            ->when($request->input('active') !== null, fn ($q) => $q->where('is_active', $request->boolean('active')))
            ->when($request->input('search'), fn ($q, $v) => $q->where(fn ($sq) => $sq->where('name', 'like', "%{$v}%")
                ->orWhere('code', 'like', "%{$v}%")))
            ->orderBy('name')
            ->paginate(50);

        return response()->json($q);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:200',
            'code' => 'required|string|max:50|unique:logistics_locations,code',
            'type' => 'required|in:zone,aisle,rack,bin,dock_receive,dock_ship,staging',
            'location_class' => 'nullable|in:storage,pick,bulk,cold,hazmat,quarantine',
            'parent_id' => 'nullable|integer|exists:logistics_locations,id',
            'warehouse_id' => 'nullable|integer',
            'capacity_units' => 'nullable|numeric|min:0',
            'max_weight_kg' => 'nullable|numeric|min:0',
            'temperature_class' => 'nullable|in:ambient,chilled,frozen,heated',
            'is_active' => 'nullable|boolean',
        ]);

        $id = DB::table('logistics_locations')->insertGetId(
            array_merge($data, ['created_at' => now(), 'updated_at' => now()])
        );

        $location = DB::table('logistics_locations')->find($id);

        return response()->json($location, 201);
    }

    public function show(string $location): JsonResponse
    {
        $record = DB::table('logistics_locations')->find((int) $location);
        abort_if($record === null, 404, 'Location not found.');

        $children = DB::table('logistics_locations')->where('parent_id', (int) $location)->orderBy('name')->get();

        return response()->json(array_merge((array) $record, ['children' => $children]));
    }

    public function update(Request $request, string $location): JsonResponse
    {
        $record = DB::table('logistics_locations')->find((int) $location);
        abort_if($record === null, 404, 'Location not found.');

        $data = $request->validate([
            'name' => 'sometimes|string|max:200',
            'type' => 'sometimes|in:zone,aisle,rack,bin,dock_receive,dock_ship,staging',
            'location_class' => 'sometimes|in:storage,pick,bulk,cold,hazmat,quarantine',
            'parent_id' => 'nullable|integer|exists:logistics_locations,id',
            'capacity_units' => 'nullable|numeric|min:0',
            'max_weight_kg' => 'nullable|numeric|min:0',
            'temperature_class' => 'nullable|in:ambient,chilled,frozen,heated',
            'is_active' => 'nullable|boolean',
        ]);

        DB::table('logistics_locations')->where('id', (int) $location)->update(
            array_merge($data, ['updated_at' => now()])
        );

        return response()->json(DB::table('logistics_locations')->find((int) $location));
    }

    public function destroy(string $location): JsonResponse
    {
        $id = (int) $location;
        $record = DB::table('logistics_locations')->find($id);
        abort_if($record === null, 404, 'Location not found.');

        abort_if(
            DB::table('logistics_locations')->where('parent_id', $id)->exists(),
            422,
            'Cannot delete a location that has child locations.'
        );

        DB::table('logistics_locations')->where('id', $id)->delete();

        return response()->json(null, 204);
    }

    public function hierarchy(Request $request): JsonResponse
    {
        $warehouseId = $request->input('warehouse_id');

        $all = DB::table('logistics_locations')
            ->when($warehouseId, fn ($q, $v) => $q->where('warehouse_id', $v))
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->keyBy('id');

        $tree = $this->buildTree($all->toArray(), null);

        return response()->json(['data' => $tree]);
    }

    /** @param array<int, object> $items */
    private function buildTree(array $items, ?int $parentId): array
    {
        $result = [];
        foreach ($items as $item) {
            if ($item->parent_id === $parentId) {
                $children = $this->buildTree($items, $item->id);
                $node = (array) $item;
                $node['children'] = $children;
                $result[] = $node;
            }
        }

        return $result;
    }
}
