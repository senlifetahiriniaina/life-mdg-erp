<?php

namespace Modules\Achats\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_id' => 'required|exists:achats_suppliers,id',
            'order_date' => 'required|date',
            'delivery_date' => 'nullable|date|after:order_date',
            'currency' => 'nullable|string|size:3',
            'shipping_cost' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            // Chantier 24 (volet D — traçabilité bout-en-bout) : lien
            // souple, pas de exists: contre inventory_production_orders,
            // pour ne pas coupler la validation Achats à Inventory.
            'production_order_id' => 'nullable|integer',
            // Chantier 10: PurchaseOrders/Form.vue always submits a `lines`
            // array in the same request body as the PO header — these rules
            // were previously entirely absent, so `lines` silently vanished
            // (not in $fillable, never read by the service) on every real
            // PO created through the UI. Validated here against
            // PurchaseOrderLine::$fillable; `line_total` is intentionally
            // not accepted — PurchaseOrderService::addLineItem() recomputes
            // it server-side from quantity*unit_price.
            'lines' => 'nullable|array',
            'lines.*.product_id' => 'nullable|exists:inventory_products,id',
            'lines.*.description' => 'required_with:lines|string',
            'lines.*.quantity' => 'required_with:lines|numeric|min:0.01',
            'lines.*.unit' => 'nullable|string',
            'lines.*.unit_price' => 'required_with:lines|numeric|min:0',
            'lines.*.tax_rate' => 'nullable|numeric|min:0',
        ];
    }
}
