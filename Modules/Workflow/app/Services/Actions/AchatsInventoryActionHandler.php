<?php

declare(strict_types=1);

namespace Modules\Workflow\Services\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Action handler for Achats ↔ Inventory workflow steps.
 *
 * Supported action keys:
 *   - inventory.receive_purchase_order
 *   - inventory.reserve_for_purchase
 *   - achats.auto_create_po
 *   - achats.request_quotation
 */
class AchatsInventoryActionHandler
{
    /**
     * Dispatch an action method by snake_case key.
     *
     * Called by WorkflowEngineService::executeAction() when the prefix is
     * 'achats' or 'inventory'.
     *
     * @param  string               $method   e.g. 'receive_purchase_order'
     * @param  array<string,mixed>  $params
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    public function dispatch(string $method, array $params, array $context): array
    {
        return match ($method) {
            'receive_purchase_order' => $this->receivePurchaseOrder($params, $context),
            'reserve_for_purchase'   => $this->reserveForPurchase($params, $context),
            'auto_create_po'         => $this->autoCreatePurchaseOrder($params, $context),
            'request_quotation'      => $this->requestQuotation($params, $context),
            default => ['status' => 'skipped', 'reason' => "Unknown Achats/Inventory action: {$method}"],
        };
    }

    /**
     * action: inventory.receive_purchase_order
     *
     * When a PO is confirmed as received: creates a stock entry and updates
     * inventory levels for every line item in the purchase order.
     *
     * Required context keys:
     *   - po_id        (int)    Purchase order ID
     *   - items        (array)  [['product_id' => int, 'quantity' => float, 'unit_cost' => float], …]
     *   - warehouse_id (int)    Target warehouse
     *
     * Optional context keys:
     *   - received_at  (string) ISO 8601 date — defaults to now()
     *   - notes        (string)
     */
    public function receivePurchaseOrder(array $params, array $context): array
    {
        $poId        = $context['po_id']        ?? null;
        $items       = $context['items']        ?? [];
        $warehouseId = $context['warehouse_id'] ?? null;
        $receivedAt  = $context['received_at']  ?? now()->toIso8601String();

        if (!$poId || empty($items) || !$warehouseId) {
            return [
                'status'  => 'error',
                'message' => 'Contexte incomplet : po_id, items et warehouse_id sont requis.',
            ];
        }

        $stockEntries = [];
        $errors       = [];

        foreach ($items as $item) {
            try {
                $productId = $item['product_id'] ?? null;
                $quantity  = (float) ($item['quantity'] ?? 0);
                $unitCost  = (float) ($item['unit_cost'] ?? 0);

                if (!$productId || $quantity <= 0) {
                    $errors[] = "Ligne ignorée : product_id={$productId}, quantity={$quantity}";
                    continue;
                }

                // Create stock movement record
                $movementId = DB::table('inventory_movements')->insertGetId([
                    'product_id'    => $productId,
                    'warehouse_id'  => $warehouseId,
                    'type'          => 'purchase_receipt',
                    'quantity'      => $quantity,
                    'unit_cost'     => $unitCost,
                    'reference_type' => 'purchase_order',
                    'reference_id'  => $poId,
                    'notes'         => $context['notes'] ?? null,
                    'created_at'    => $receivedAt,
                    'updated_at'    => $receivedAt,
                ]);

                // Update current stock level
                DB::table('inventory_stock_levels')
                    ->where('product_id', $productId)
                    ->where('warehouse_id', $warehouseId)
                    ->increment('quantity_on_hand', $quantity);

                // Release any reservation created during the PO phase
                DB::table('inventory_reservations')
                    ->where('product_id', $productId)
                    ->where('reference_type', 'purchase_order')
                    ->where('reference_id', $poId)
                    ->delete();

                $stockEntries[] = [
                    'movement_id' => $movementId,
                    'product_id'  => $productId,
                    'quantity'    => $quantity,
                    'unit_cost'   => $unitCost,
                ];
            } catch (\Throwable $e) {
                Log::error('AchatsInventoryActionHandler::receivePurchaseOrder item error', [
                    'item'      => $item,
                    'exception' => $e->getMessage(),
                ]);
                $errors[] = "Erreur produit {$item['product_id']}: {$e->getMessage()}";
            }
        }

        // Mark PO as received
        try {
            DB::table('achats_purchase_orders')
                ->where('id', $poId)
                ->update([
                    'status'      => 'received',
                    'received_at' => $receivedAt,
                    'updated_at'  => now(),
                ]);
        } catch (\Throwable $e) {
            Log::warning('AchatsInventoryActionHandler: could not update PO status', [
                'po_id'     => $poId,
                'exception' => $e->getMessage(),
            ]);
        }

        return [
            'status'        => empty($errors) ? 'success' : 'partial',
            'action'        => 'inventory.receive_purchase_order',
            'po_id'         => $poId,
            'warehouse_id'  => $warehouseId,
            'stock_entries' => $stockEntries,
            'errors'        => $errors,
            'message'       => sprintf(
                '%d ligne(s) réceptionnée(s) en stock (entrepôt #%d).',
                count($stockEntries),
                $warehouseId,
            ),
        ];
    }

    /**
     * action: inventory.reserve_for_purchase
     *
     * Reserves a quantity of a product so it is not allocated elsewhere while a
     * purchase order is being processed.
     *
     * Required context keys:
     *   - product_id  (int)
     *   - quantity    (float)
     *   - po_id       (int)    Originating purchase order
     *   - warehouse_id (int)
     */
    public function reserveForPurchase(array $params, array $context): array
    {
        $productId   = $context['product_id']   ?? null;
        $quantity    = (float) ($context['quantity'] ?? 0);
        $poId        = $context['po_id']        ?? null;
        $warehouseId = $context['warehouse_id'] ?? null;

        if (!$productId || $quantity <= 0 || !$poId || !$warehouseId) {
            return [
                'status'  => 'error',
                'message' => 'Contexte incomplet : product_id, quantity, po_id et warehouse_id sont requis.',
            ];
        }

        try {
            $reservationId = DB::table('inventory_reservations')->insertGetId([
                'product_id'     => $productId,
                'warehouse_id'   => $warehouseId,
                'quantity'       => $quantity,
                'reference_type' => 'purchase_order',
                'reference_id'   => $poId,
                'status'         => 'active',
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);

            return [
                'status'         => 'success',
                'action'         => 'inventory.reserve_for_purchase',
                'reservation_id' => $reservationId,
                'product_id'     => $productId,
                'quantity'       => $quantity,
                'po_id'          => $poId,
                'message'        => "Réservation de {$quantity} unité(s) du produit #{$productId} créée pour le BC #{$poId}.",
            ];
        } catch (\Throwable $e) {
            Log::error('AchatsInventoryActionHandler::reserveForPurchase error', [
                'context'   => $context,
                'exception' => $e->getMessage(),
            ]);

            return [
                'status'  => 'error',
                'message' => "Erreur lors de la réservation : {$e->getMessage()}",
            ];
        }
    }

    /**
     * action: achats.auto_create_po
     *
     * Automatically creates a purchase order when a product's stock falls below
     * its reorder point and the product has a preferred supplier configured.
     *
     * Required context keys:
     *   - product_id           (int)
     *   - current_stock        (float)
     *   - reorder_point        (float)
     *   - preferred_supplier_id (int)
     *
     * Optional context keys:
     *   - order_quantity       (float)  Defaults to (reorder_point * 2 - current_stock)
     *   - currency             (string) Defaults to 'XOF'
     *   - warehouse_id         (int)
     */
    public function autoCreatePurchaseOrder(array $params, array $context): array
    {
        $productId          = $context['product_id']            ?? null;
        $currentStock       = (float) ($context['current_stock']  ?? 0);
        $reorderPoint       = (float) ($context['reorder_point']  ?? 0);
        $preferredSupplierId = $context['preferred_supplier_id'] ?? null;
        $currency           = $context['currency']              ?? 'XOF';
        $warehouseId        = $context['warehouse_id']          ?? null;

        if (!$productId || !$preferredSupplierId) {
            return [
                'status'  => 'error',
                'message' => 'Contexte incomplet : product_id et preferred_supplier_id sont requis.',
            ];
        }

        if ($currentStock >= $reorderPoint) {
            return [
                'status'  => 'skipped',
                'action'  => 'achats.auto_create_po',
                'message' => "Stock actuel ({$currentStock}) ≥ seuil de réapprovisionnement ({$reorderPoint}). BC non créé.",
            ];
        }

        // Calculate order quantity: enough to reach 2× the reorder point
        $orderQuantity = $context['order_quantity']
            ?? max(1, ($reorderPoint * 2) - $currentStock);

        try {
            $poNumber = 'AUTO-' . strtoupper(substr(md5(uniqid()), 0, 8));

            $poId = DB::table('achats_purchase_orders')->insertGetId([
                'po_number'    => $poNumber,
                'supplier_id'  => $preferredSupplierId,
                'warehouse_id' => $warehouseId,
                'currency'     => $currency,
                'status'       => 'draft',
                'origin'       => 'workflow_auto',
                'notes'        => "BC automatique — stock {$currentStock} < seuil {$reorderPoint}",
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);

            DB::table('achats_purchase_order_lines')->insert([
                'purchase_order_id' => $poId,
                'product_id'        => $productId,
                'quantity'          => $orderQuantity,
                'unit_price'        => 0, // To be filled after quotation
                'currency'          => $currency,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);

            return [
                'status'         => 'success',
                'action'         => 'achats.auto_create_po',
                'po_id'          => $poId,
                'po_number'      => $poNumber,
                'supplier_id'    => $preferredSupplierId,
                'product_id'     => $productId,
                'order_quantity' => $orderQuantity,
                'currency'       => $currency,
                'message'        => "BC automatique #{$poNumber} créé pour {$orderQuantity} unité(s) du produit #{$productId} auprès du fournisseur #{$preferredSupplierId}.",
            ];
        } catch (\Throwable $e) {
            Log::error('AchatsInventoryActionHandler::autoCreatePurchaseOrder error', [
                'context'   => $context,
                'exception' => $e->getMessage(),
            ]);

            return [
                'status'  => 'error',
                'message' => "Erreur lors de la création du BC automatique : {$e->getMessage()}",
            ];
        }
    }

    /**
     * action: achats.request_quotation
     *
     * Sends a request for quotation (RFQ) to one or more suppliers for a
     * given product or list of products.
     *
     * Required context keys:
     *   - product_id   (int)   or  items (array)
     *   - supplier_ids (array) List of supplier IDs to contact
     *
     * Optional context keys:
     *   - quantity     (float)
     *   - deadline     (string) ISO 8601 date for quotation deadline
     *   - currency     (string) Defaults to 'XOF'
     *   - notes        (string)
     */
    public function requestQuotation(array $params, array $context): array
    {
        $supplierIds = $context['supplier_ids'] ?? [];
        $productId   = $context['product_id']   ?? null;
        $items       = $context['items']        ?? [];
        $currency    = $context['currency']     ?? 'XOF';
        $deadline    = $context['deadline']     ?? now()->addDays(7)->toIso8601String();

        if (empty($supplierIds)) {
            return [
                'status'  => 'error',
                'message' => 'Contexte incomplet : supplier_ids est requis.',
            ];
        }

        if ($productId && empty($items)) {
            $items = [['product_id' => $productId, 'quantity' => $context['quantity'] ?? 1]];
        }

        if (empty($items)) {
            return [
                'status'  => 'error',
                'message' => 'Contexte incomplet : product_id ou items est requis.',
            ];
        }

        $rfqIds = [];

        try {
            foreach ($supplierIds as $supplierId) {
                $rfqNumber = 'RFQ-' . strtoupper(substr(md5(uniqid()), 0, 8));

                $rfqId = DB::table('achats_rfqs')->insertGetId([
                    'rfq_number'  => $rfqNumber,
                    'supplier_id' => $supplierId,
                    'currency'    => $currency,
                    'deadline'    => $deadline,
                    'status'      => 'sent',
                    'origin'      => 'workflow',
                    'notes'       => $context['notes'] ?? null,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);

                foreach ($items as $item) {
                    DB::table('achats_rfq_lines')->insert([
                        'rfq_id'     => $rfqId,
                        'product_id' => $item['product_id'],
                        'quantity'   => $item['quantity'] ?? 1,
                        'currency'   => $currency,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $rfqIds[] = ['rfq_id' => $rfqId, 'rfq_number' => $rfqNumber, 'supplier_id' => $supplierId];
            }

            return [
                'status'  => 'success',
                'action'  => 'achats.request_quotation',
                'rfqs'    => $rfqIds,
                'message' => sprintf(
                    'Demande de devis envoyée à %d fournisseur(s).',
                    count($rfqIds),
                ),
            ];
        } catch (\Throwable $e) {
            Log::error('AchatsInventoryActionHandler::requestQuotation error', [
                'context'   => $context,
                'exception' => $e->getMessage(),
            ]);

            return [
                'status'  => 'error',
                'message' => "Erreur lors de la création de la demande de devis : {$e->getMessage()}",
            ];
        }
    }
}
