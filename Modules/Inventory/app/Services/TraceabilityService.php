<?php

declare(strict_types=1);

namespace Modules\Inventory\Services;

use Modules\Achats\Models\PurchaseOrder;
use Modules\Inventory\Models\ProductionOrder;
use Modules\Sales\Models\SalesOrder;

/**
 * Chantier 24 (volet D de la feuille de route Chantier 21) — traçabilité
 * bout-en-bout : reconstitue, en lecture seule, le fil complet d'une
 * commande de production (ProductionOrder), qui sert d'ancre pour
 * rassembler devis chiffré (CostingSheet, Chantier 21), commande client
 * avec acompte/solde (SalesOrder, Chantier 22), achats matières liés
 * (PurchaseOrder via le lien souple production_order_id, ce chantier), et
 * le sous-traitant. Ne modifie rien — un pur agrégateur de ce qui existe
 * déjà, pas un nouveau modèle de données de suivi parallèle.
 */
class TraceabilityService
{
    public function build(ProductionOrder $order): array
    {
        $order->loadMissing(['costingSheet', 'subcontractor']);

        $salesOrder = $order->sales_order_id !== null
            ? SalesOrder::with(['depositInvoice', 'balanceInvoice'])->find($order->sales_order_id)
            : null;

        $purchaseOrders = PurchaseOrder::with('supplier')
            ->where('production_order_id', $order->id)
            ->orderBy('order_date')
            ->get();

        return [
            'production_order' => [
                'id' => $order->id,
                'reference' => $order->reference,
                'status' => $order->status,
                'quantity' => $order->quantity,
                'started_at' => $order->started_at?->toDateString(),
                'expected_delivery_at' => $order->expected_delivery_at?->toDateString(),
                'delivered_at' => $order->delivered_at?->toDateString(),
            ],
            'costing_sheet' => $order->costingSheet ? [
                'id' => $order->costingSheet->id,
                'reference' => $order->costingSheet->reference,
                'name' => $order->costingSheet->name,
                'total_cost_price' => (float) $order->costingSheet->total_cost_price,
                'suggested_selling_price' => (float) $order->costingSheet->suggested_selling_price,
                'base_currency' => $order->costingSheet->base_currency,
            ] : null,
            'sales_order' => $salesOrder ? [
                'id' => $salesOrder->id,
                'reference' => $salesOrder->reference,
                'status' => $salesOrder->status,
                'total' => (float) $salesOrder->total,
                'currency' => $salesOrder->currency,
                'payment_stage' => $salesOrder->payment_stage,
            ] : null,
            'subcontractor' => $order->subcontractor ? [
                'id' => $order->subcontractor->id,
                'name' => $order->subcontractor->name,
                'code' => $order->subcontractor->code,
            ] : null,
            'material_purchase_orders' => $purchaseOrders->map(fn (PurchaseOrder $po) => [
                'id' => $po->id,
                'po_number' => $po->po_number,
                'supplier_name' => $po->supplier?->name,
                'status' => $po->status,
                'total' => (float) $po->total,
                'currency' => $po->currency,
                'payment_stage' => $po->payment_stage,
            ])->values()->all(),
        ];
    }
}
