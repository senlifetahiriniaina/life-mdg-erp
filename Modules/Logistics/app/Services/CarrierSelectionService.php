<?php

declare(strict_types=1);

namespace Modules\Logistics\Services;

use Modules\Logistics\Models\Carrier;
use Modules\Logistics\Models\CarrierRate;

class CarrierSelectionService
{
    /** @return array<int, array<string, mixed>> */
    public function selectCarrier(array $criteria): array
    {
        $mode = $criteria['transport_mode'] ?? null;
        $weight = (float) ($criteria['weight_kg'] ?? 0);
        $priority = $criteria['priority'] ?? 'cheapest';

        $rates = CarrierRate::with('carrier')
            ->where('is_active', true)
            ->where('origin_country', $criteria['origin_country'])
            ->where('destination_country', $criteria['destination_country'])
            ->when($mode, fn ($q, $v) => $q->where('mode', $v))
            ->get();

        $options = $rates->map(function (CarrierRate $rate) use ($weight) {
            $cost = $this->calculateCost($rate, $weight);

            return [
                'carrier' => $rate->carrier,
                'rate' => $rate,
                'estimated_cost' => $cost,
                'transit_days' => $rate->transit_days,
                'currency' => $rate->currency,
                'score' => $rate->carrier->rating ?? 0,
            ];
        });

        return match ($priority) {
            'fastest' => $options->sortBy('transit_days')->values()->toArray(),
            'greenest' => $options->sortByDesc('score')->values()->toArray(),
            default => $options->sortBy('estimated_cost')->values()->toArray(),
        };
    }

    private function calculateCost(CarrierRate $rate, float $weight): float
    {
        $base = (float) $rate->base_rate;

        $cost = match ($rate->rate_type) {
            'per_kg' => $base * max($weight, 1),
            'flat' => $base,
            default => $base,
        };

        $cost += $cost * ((float) $rate->fuel_surcharge_pct / 100);

        return max($cost, (float) $rate->min_charge);
    }

    /** @return array<string, mixed> */
    public function getCarrierPerformance(Carrier $carrier): array
    {
        $shipments = $carrier->shipments();
        $total = $shipments->count();
        $delivered = $shipments->where('status', 'delivered')->count();
        $onTime = $shipments->whereColumn('delivered_at', '<=', 'estimated_delivery_at')
            ->whereNotNull('delivered_at')->count();
        $avgCost = $shipments->whereNotNull('actual_cost')->avg('actual_cost');
        $avgTransit = $shipments->whereNotNull('delivered_at')->whereNotNull('picked_up_at')
            ->selectRaw('AVG(julianday(delivered_at) - julianday(picked_up_at)) as avg_days')
            ->value('avg_days');

        return [
            'total_shipments' => $total,
            'delivered' => $delivered,
            'delivery_rate_pct' => $total > 0 ? round($delivered / $total * 100, 1) : 0,
            'on_time_rate_pct' => $delivered > 0 ? round($onTime / $delivered * 100, 1) : 0,
            'avg_cost' => $avgCost ? round((float) $avgCost, 2) : null,
            'avg_transit_days' => $avgTransit ? round((float) $avgTransit, 1) : null,
            'rating' => $carrier->rating,
        ];
    }
}
