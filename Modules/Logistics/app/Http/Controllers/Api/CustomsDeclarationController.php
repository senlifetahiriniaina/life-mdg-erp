<?php

declare(strict_types=1);

namespace Modules\Logistics\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Logistics\Models\CustomsDeclaration;

/**
 * @group Logistics - Customs Declarations
 */
class CustomsDeclarationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = CustomsDeclaration::query()
            ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->input('type'), fn ($q, $v) => $q->where('type', $v))
            ->when($request->input('shipment_id'), fn ($q, $v) => $q->where('shipment_id', $v))
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => $q->items(),
            'meta' => [
                'total' => $q->total(),
                'per_page' => $q->perPage(),
                'current_page' => $q->currentPage(),
                'last_page' => $q->lastPage(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'shipment_id' => 'required|integer|exists:logistics_shipments,id',
            'type' => 'nullable|in:export,import,transit',
            'hs_code' => ['nullable', 'string', 'regex:/^\d{4}(\.\d{2}(\.\d{2})?)?$/'],
            'item_description' => 'nullable|string',
            'quantity' => 'nullable|numeric|min:0',
            'declared_value' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
            'country_of_origin' => 'nullable|string|size:2',
            'declaration_number' => 'nullable|string|unique:logistics_customs_declarations,declaration_number',
            'country_export' => 'nullable|string|size:2',
            'country_import' => 'nullable|string|size:2',
            'incoterm' => 'nullable|in:EXW,FCA,CPT,CIP,DAP,DDP,FOB,CFR,CIF',
            'total_declared_value' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $data['created_by'] = $request->user()->id;
        $data['status'] = 'draft';

        $declaration = CustomsDeclaration::create($data);
        return response()->json(['data' => $declaration], 201);
    }

    public function show(CustomsDeclaration $customsDeclaration): JsonResponse
    {
        return response()->json(['data' => $customsDeclaration]);
    }

    public function update(Request $request, CustomsDeclaration $customsDeclaration): JsonResponse
    {
        $data = $request->validate([
            'hs_code' => 'nullable|string',
            'item_description' => 'nullable|string',
            'declared_value' => 'nullable|numeric|min:0',
            'total_declared_value' => 'sometimes|numeric|min:0',
            'currency' => 'sometimes|string|size:3',
            'notes' => 'nullable|string',
            'incoterm' => 'nullable|in:EXW,FCA,CPT,CIP,DAP,DDP,FOB,CFR,CIF',
        ]);

        $customsDeclaration->update($data);

        return response()->json(['data' => $customsDeclaration->fresh()]);
    }

    public function destroy(CustomsDeclaration $customsDeclaration): JsonResponse
    {
        $customsDeclaration->delete();

        return response()->json(['message' => 'Deleted.']);
    }

    public function submit(CustomsDeclaration $customsDeclaration): JsonResponse
    {
        abort_if($customsDeclaration->status !== 'draft', 422, 'Only draft declarations can be submitted.');

        $customsDeclaration->update([
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        return response()->json($customsDeclaration->fresh());
    }
}
