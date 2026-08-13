<?php

declare(strict_types=1);

namespace Modules\Logistics\Services;

use Carbon\Carbon;
use Modules\Logistics\Models\Shipment;

class ShipmentService
{
    public function create(array $data, int $userId): Shipment
    {
        $lines = $data['lines'] ?? [];
        unset($data['lines']);

        $data['created_by'] = $userId;
        $data['reference'] = $this->generateReference();
        $data['status'] = $data['status'] ?? 'draft';

        $shipment = Shipment::create($data);

        foreach ($lines as $line) {
            $shipment->lines()->create($line);
        }

        return $shipment;
    }

    public function book(Shipment $shipment): void
    {
        $shipment->update([
            'status' => 'booked',
            'booked_at' => now(),
        ]);

        $shipment->trackingEvents()->create([
            'event_type' => 'booked',
            'status_detail' => 'Shipment booked and confirmed',
            'recorded_at' => now(),
        ]);
    }

    public function dispatch(Shipment $shipment): void
    {
        $shipment->update([
            'status' => 'picked_up',
            'picked_up_at' => now(),
        ]);

        $shipment->trackingEvents()->create([
            'event_type' => 'picked_up',
            'status_detail' => 'Shipment picked up by carrier',
            'recorded_at' => now(),
        ]);
    }

    public function deliver(Shipment $shipment, ?string $deliveredAt): void
    {
        $ts = $deliveredAt ? Carbon::parse($deliveredAt) : now();

        $shipment->update(['status' => 'delivered', 'delivered_at' => $ts]);

        $shipment->trackingEvents()->create([
            'event_type' => 'delivered',
            'status_detail' => 'Shipment delivered to consignee',
            'recorded_at' => $ts,
        ]);
    }

    public function cancel(Shipment $shipment): void
    {
        $shipment->update(['status' => 'cancelled']);

        $shipment->trackingEvents()->create([
            'event_type' => 'cancelled',
            'status_detail' => 'Shipment cancelled',
            'recorded_at' => now(),
        ]);
    }

    private function generateReference(): string
    {
        $date = now()->format('Ymd');
        $last = Shipment::whereDate('created_at', today())->count() + 1;

        return 'SHP-'.$date.'-'.str_pad((string) $last, 4, '0', STR_PAD_LEFT);
    }
}
