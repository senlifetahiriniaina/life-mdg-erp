<?php

namespace Modules\Achats\Services;

use Illuminate\Database\Eloquent\Collection;
use Modules\Achats\Models\Supplier;

class SupplierService
{
    public function createSupplier(array $data): Supplier
    {
        $data['code'] = $data['code'] ?? $this->generateSupplierCode();
        $data['currency'] = $data['currency'] ?? config('achats.default_currency', 'USD');
        $data['lead_time_days'] = $data['lead_time_days'] ?? 7;
        $data['is_active'] = $data['is_active'] ?? true;

        return Supplier::create($data);
    }

    public function updateSupplier(Supplier $supplier, array $data): Supplier
    {
        $supplier->update($data);

        return $supplier;
    }

    public function deactivateSupplier(Supplier $supplier): void
    {
        $supplier->update(['is_active' => false]);
    }

    public function activateSupplier(Supplier $supplier): void
    {
        $supplier->update(['is_active' => true]);
    }

    public function getActiveSuppliers(): Collection
    {
        return Supplier::where('is_active', true)->get();
    }

    public function getSupplierByCode(string $code): ?Supplier
    {
        return Supplier::where('code', $code)->first();
    }

    public function getSupplierQuoteHistory(Supplier $supplier): Collection
    {
        return $supplier->quotes()->orderBy('created_at', 'desc')->get();
    }

    public function getSupplierPerformanceMetrics(Supplier $supplier): array
    {
        $purchaseOrders = $supplier->purchaseOrders()
            ->where('status', 'received')
            ->get();

        if ($purchaseOrders->isEmpty()) {
            return [
                'total_orders' => 0,
                'total_spent' => 0,
                'average_order_value' => 0,
                'on_time_delivery' => 0,
                'quality_score' => 0,
            ];
        }

        $totalOrders = $purchaseOrders->count();
        $totalSpent = $purchaseOrders->sum('total');
        $averageOrderValue = $totalSpent / $totalOrders;

        // Calculate on-time delivery percentage
        $onTimeDeliveries = $purchaseOrders->filter(function ($po) {
            return $po->delivery_date && $po->delivery_date->lte(now());
        })->count();

        $onTimePercentage = ($onTimeDeliveries / $totalOrders) * 100;

        // Quality score based on receipts with no issues
        $receipts = $supplier->purchaseOrders()
            ->with('receipt')
            ->get()
            ->filter(fn ($po) => $po->receipt)
            ->map(fn ($po) => $po->receipt);

        $qualityScore = $receipts->isEmpty() ? 100 :
            ($receipts->filter(fn ($receipt) => ! $receipt->hasDiscrepancies())->count() / $receipts->count()) * 100;

        return [
            'total_orders' => $totalOrders,
            'total_spent' => $totalSpent,
            'average_order_value' => $averageOrderValue,
            'on_time_delivery' => round($onTimePercentage, 2),
            'quality_score' => round($qualityScore, 2),
        ];
    }

    public function generateSupplierCode(): string
    {
        $prefix = 'SUP';
        $sequence = Supplier::count() + 1;

        return $prefix.'-'.str_pad($sequence, 6, '0', STR_PAD_LEFT);
    }

    public function deleteSupplier(Supplier $supplier): void
    {
        if ($supplier->purchaseOrders()->exists()) {
            throw new \Exception('Cannot delete supplier with existing purchase orders');
        }

        $supplier->delete();
    }
}
