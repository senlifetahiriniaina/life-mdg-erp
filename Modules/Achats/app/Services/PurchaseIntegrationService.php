<?php

namespace Modules\Achats\Services;

use Modules\Achats\Events\PurchaseOrderReadyForAccounting;
use Modules\Achats\Events\PurchaseOrderReadyForInvoicing;
use Modules\Achats\Events\PurchaseReceiptReadyForInventory;
use Modules\Achats\Models\PoAccountingMapping;
use Modules\Achats\Models\PoBudgetAllocation;
use Modules\Achats\Models\PoInventoryMapping;
use Modules\Achats\Models\PurchaseOrder;
use Modules\Achats\Models\PurchaseReceipt;

class PurchaseIntegrationService
{
    public function syncPOToAccounting(PurchaseOrder $po): void
    {
        PoAccountingMapping::updateOrCreate(
            ['purchase_order_id' => $po->id],
            [
                'status' => 'draft',
                'synced_amount' => $po->total,
            ]
        );

        event(new PurchaseOrderReadyForAccounting($po));
    }

    public function syncReceiptToInventory(PurchaseReceipt $receipt): void
    {
        foreach ($receipt->lines as $line) {
            PoInventoryMapping::updateOrCreate(
                [
                    'purchase_order_id' => $receipt->purchase_order_id,
                    'product_id' => $line->product_id,
                ],
                [
                    'receipt_id' => $receipt->id,
                    'received_qty' => $line->quantity_received,
                    'status' => 'pending',
                ]
            );
        }

        event(new PurchaseReceiptReadyForInventory($receipt));
    }

    public function checkBudgetAvailability(PurchaseOrder $po, string $costCenter): bool
    {
        $allocation = $po->budgetAllocation()->first();

        if (! $allocation) {
            return true;
        }

        return $allocation->checkBudgetAvailable((float) $po->total);
    }

    public function allocateBudget(PurchaseOrder $po, string $costCenter, float $amount): void
    {
        $allocation = PoBudgetAllocation::updateOrCreate(
            ['purchase_order_id' => $po->id],
            [
                'cost_center_id' => $costCenter,
                'allocated_amount' => $amount,
                'budget_type' => 'operational',
                'status' => 'active',
            ]
        );

        $allocation->allocate($amount);
    }

    public function releaseBudget(PurchaseOrder $po): void
    {
        $allocation = $po->budgetAllocation()->first();

        if ($allocation) {
            $allocation->release();
        }
    }

    public function generateInvoiceFromPO(PurchaseOrder $po): int
    {
        // This would be implemented to integrate with the Accounting module
        // For now, returning a placeholder ID
        event(new PurchaseOrderReadyForInvoicing($po));

        return 0;
    }

    public function updatePOIntegrationStatus(PurchaseOrder $po, string $status): void
    {
        $accountingMapping = $po->accountingMapping;

        if ($accountingMapping) {
            $accountingMapping->update(['status' => $status]);
        }

        $inventoryMappings = $po->inventoryMappings;

        foreach ($inventoryMappings as $mapping) {
            $mapping->update(['status' => $status]);
        }
    }

    public function getPOIntegrationStatus(PurchaseOrder $po): array
    {
        return [
            'accounting' => $po->accountingMapping?->status ?? 'pending',
            'inventory' => $po->inventoryMappings->pluck('status')->unique()->join(', '),
            'budget' => $po->budgetAllocation?->status ?? 'not_allocated',
        ];
    }
}
