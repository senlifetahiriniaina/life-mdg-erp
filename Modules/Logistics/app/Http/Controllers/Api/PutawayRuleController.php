<?php

declare(strict_types=1);

namespace Modules\Logistics\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @group Logistics - Putaway Rules
 *
 * Rules for directing inbound stock to warehouse locations automatically.
 */
class PutawayRuleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = DB::table('logistics_putaway_rules')
            ->when($request->input('location_id'), fn ($q, $v) => $q->where('location_id', $v))
            ->when($request->input('active') !== null, fn ($q) => $q->where('is_active', $request->boolean('active')))
            ->when($request->input('product_category'), fn ($q, $v) => $q->where('product_category', $v))
            ->when($request->input('search'), fn ($q, $v) => $q->where(fn ($sq) => $sq->where('name', 'like', "%{$v}%")
                ->orWhere('product_category', 'like', "%{$v}%")))
            ->orderBy('priority')
            ->orderBy('name')
            ->paginate(50);

        return response()->json($q);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:200',
            'location_id' => 'required|integer',
            'product_id' => 'nullable|integer',
            'product_category' => 'nullable|string|max:100',
            'carrier_id' => 'nullable|integer|exists:logistics_carriers,id',
            'transport_mode' => 'nullable|in:road,air,sea,rail,multimodal',
            'requires_cold_chain' => 'nullable|boolean',
            'has_hazmat' => 'nullable|boolean',
            'priority' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'notes' => 'nullable|string',
        ]);

        $id = DB::table('logistics_putaway_rules')->insertGetId(
            array_merge($data, ['created_at' => now(), 'updated_at' => now()])
        );

        return response()->json(DB::table('logistics_putaway_rules')->find($id), 201);
    }

    public function show(int $putawayRule): JsonResponse
    {
        $record = DB::table('logistics_putaway_rules')->find($putawayRule);
        abort_if($record === null, 404, 'Putaway rule not found.');

        return response()->json($record);
    }

    public function update(Request $request, int $putawayRule): JsonResponse
    {
        $record = DB::table('logistics_putaway_rules')->find($putawayRule);
        abort_if($record === null, 404, 'Putaway rule not found.');

        $data = $request->validate([
            'name' => 'sometimes|string|max:200',
            'location_id' => 'sometimes|integer',
            'product_id' => 'nullable|integer',
            'product_category' => 'nullable|string|max:100',
            'carrier_id' => 'nullable|integer|exists:logistics_carriers,id',
            'transport_mode' => 'nullable|in:road,air,sea,rail,multimodal',
            'requires_cold_chain' => 'nullable|boolean',
            'has_hazmat' => 'nullable|boolean',
            'priority' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'notes' => 'nullable|string',
        ]);

        DB::table('logistics_putaway_rules')->where('id', $putawayRule)->update(
            array_merge($data, ['updated_at' => now()])
        );

        return response()->json(DB::table('logistics_putaway_rules')->find($putawayRule));
    }

    public function destroy(int $putawayRule): JsonResponse
    {
        $record = DB::table('logistics_putaway_rules')->find($putawayRule);
        abort_if($record === null, 404, 'Putaway rule not found.');

        DB::table('logistics_putaway_rules')->where('id', $putawayRule)->delete();

        return response()->json(null, 204);
    }
}
