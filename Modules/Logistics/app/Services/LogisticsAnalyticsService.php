<?php

declare(strict_types=1);

namespace Modules\Logistics\Services;

use Modules\Logistics\Models\Carrier;
use Modules\Logistics\Models\Shipment;

class LogisticsAnalyticsService
{
    /** @return array<string, mixed> */
    public function kpis(): array
    {
        $total = Shipment::count();
        $today = Shipment::whereDate('created_at', today())->count();
        $delivered = Shipment::where('status', 'delivered')->count();
        $inTransit = Shipment::where('status', 'in_transit')->count();
        $exceptions = Shipment::whereHas('trackingEvents', fn ($q) => $q->where('is_exception', true))->count();
        $onTime = Shipment::whereColumn('delivered_at', '<=', 'estimated_delivery_at')
            ->whereNotNull('delivered_at')->count();
        $avgCost = Shipment::whereNotNull('actual_cost')->avg('actual_cost');
        $totalCo2 = Shipment::whereNotNull('co2_kg')->sum('co2_kg');

        return [
            'total_shipments' => $total,
            'today' => $today,
            'delivered' => $delivered,
            'in_transit' => $inTransit,
            'exceptions' => $exceptions,
            'on_time_rate_pct' => $delivered > 0 ? round($onTime / $delivered * 100, 1) : 0,
            'exception_rate_pct' => $total > 0 ? round($exceptions / $total * 100, 1) : 0,
            'avg_cost' => $avgCost ? round((float) $avgCost, 2) : null,
            'total_co2_kg' => round((float) $totalCo2, 2),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function carrierPerformance(): array
    {
        return Carrier::withCount('shipments')
            ->with(['shipments' => fn ($q) => $q->select('carrier_id', 'status', 'delivered_at', 'estimated_delivery_at', 'actual_cost')])
            ->orderByDesc('shipments_count')
            ->limit(10)
            ->get()
            ->map(fn (Carrier $c) => [
                'carrier_id' => $c->id,
                'carrier_name' => $c->name,
                'total' => $c->shipments_count,
                'rating' => $c->rating,
            ])
            ->toArray();
    }

    /** @return array<string, mixed> */
    public function shipmentStats(array $filters = []): array
    {
        $q = Shipment::query();

        if (! empty($filters['date_from'])) {
            $q->where('created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $q->where('created_at', '<=', $filters['date_to']);
        }

        $byStatus = (clone $q)->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')->pluck('count', 'status');
        $byMode = (clone $q)->selectRaw('transport_mode, COUNT(*) as count')
            ->whereNotNull('transport_mode')->groupBy('transport_mode')->pluck('count', 'transport_mode');
        $byType = (clone $q)->selectRaw('type, COUNT(*) as count')
            ->groupBy('type')->pluck('count', 'type');

        return [
            'by_status' => $byStatus,
            'by_mode' => $byMode,
            'by_type' => $byType,
        ];
    }

    /** @return array<string, mixed> */
    public function co2Emissions(): array
    {
        $total = Shipment::whereNotNull('co2_kg')->sum('co2_kg');
        $byMode = Shipment::whereNotNull('co2_kg')->whereNotNull('transport_mode')
            ->selectRaw('transport_mode, SUM(co2_kg) as total, COUNT(*) as count')
            ->groupBy('transport_mode')->get()
            ->map(fn ($r) => [
                'mode' => $r->transport_mode,
                'total_kg' => round((float) $r->total, 2),
                'count' => $r->count,
            ]);

        return [
            'total_co2_kg' => round((float) $total, 2),
            'by_mode' => $byMode,
        ];
    }
}
