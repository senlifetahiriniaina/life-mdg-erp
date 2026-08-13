<?php

declare(strict_types=1);

namespace Modules\Inventory\Services;

use Modules\Inventory\Models\Carrier;
use Modules\Inventory\Models\Shipment;
use Modules\Inventory\Models\ShipmentEvent;

class ShippingService
{
    /**
     * Calculate shipping rates for a parcel (mock: returns simulated rates per carrier).
     *
     * @param  array<string, mixed>  $originAddress
     * @param  array<string, mixed>  $destAddress
     * @return array<int, array<string, mixed>>
     */
    public function getRates(array $originAddress, array $destAddress, float $weightKg): array
    {
        $carriers = Carrier::where('active', true)->get();
        $rates = [];

        $baseRates = [
            'dhl' => ['express' => [12.50, 1], 'standard' => [8.90, 3], 'economy' => [6.50, 5]],
            'fedex' => ['express' => [14.00, 1], 'standard' => [9.50, 3], 'economy' => [7.00, 5]],
            'ups' => ['express' => [13.00, 1], 'standard' => [9.00, 3], 'economy' => [6.80, 5]],
            'colissimo' => ['standard' => [7.50, 4], 'economy' => [5.90, 6]],
            'chronopost' => ['express' => [11.50, 1], 'standard' => [8.50, 2]],
            'gls' => ['standard' => [8.00, 3], 'economy' => [6.20, 5]],
        ];

        foreach ($carriers as $carrier) {
            $carrierRates = $baseRates[$carrier->code] ?? ['standard' => [10.00, 3]];

            foreach ($carrierRates as $service => [$basePrice, $days]) {
                // Adjust price by weight
                $weightSurcharge = max(0, ($weightKg - 1) * 0.80);
                $price = round($basePrice + $weightSurcharge, 2);

                $rates[] = [
                    'carrier_id' => $carrier->id,
                    'carrier_name' => $carrier->name,
                    'carrier_code' => $carrier->code,
                    'service' => $service,
                    'price' => number_format($price, 2, '.', ''),
                    'days' => $days,
                    'currency' => 'EUR',
                ];
            }
        }

        // Sort by price
        usort($rates, fn (array $a, array $b) => (float) $a['price'] <=> (float) $b['price']);

        return $rates;
    }

    /**
     * Create a shipment and generate a mock tracking number.
     *
     * @param  array<string, mixed>  $data
     */
    public function createShipment(array $data): Shipment
    {
        $trackingNumber = $this->generateTrackingNumber($data['carrier_id'] ?? 0);

        /** @var Shipment $shipment */
        $shipment = Shipment::create([
            'carrier_id' => $data['carrier_id'],
            'reference' => $this->generateReference(),
            'order_id' => $data['order_id'] ?? null,
            'status' => 'booked',
            'tracking_number' => $trackingNumber,
            'label_url' => null,
            'origin_address' => $data['origin_address'],
            'destination_address' => $data['destination_address'],
            'weight_kg' => $data['weight_kg'],
            'dimensions' => $data['dimensions'] ?? null,
            'service_type' => $data['service_type'] ?? 'standard',
            'estimated_cost' => $data['estimated_cost'] ?? null,
            'actual_cost' => null,
            'shipped_at' => null,
            'estimated_delivery_at' => $data['estimated_delivery_at'] ?? null,
            'delivered_at' => null,
        ]);

        // Create initial booking event
        $this->updateStatus($shipment, 'booked', 'Entrepôt expéditeur');

        return $shipment->load(['carrier', 'events']);
    }

    /**
     * Simulate tracking (generates mock events based on current status).
     *
     * @return array<int, array<string, mixed>>
     */
    public function track(Shipment $shipment): array
    {
        $events = $shipment->events()->orderBy('occurred_at')->get();

        if ($events->isEmpty()) {
            // Generate mock tracking events if none exist
            $this->generateMockTrackingEvents($shipment);
            $events = $shipment->events()->orderBy('occurred_at')->get();
        }

        return $events->map(fn (ShipmentEvent $event) => [
            'status' => $event->status,
            'location' => $event->location,
            'description' => $event->description,
            'occurred_at' => $event->occurred_at->toIso8601String(),
        ])->all();
    }

    /**
     * Update shipment status and create a tracking event.
     */
    public function updateStatus(Shipment $shipment, string $status, string $location = ''): ShipmentEvent
    {
        $statusDescriptions = [
            'draft' => 'Expédition créée, en attente de prise en charge',
            'booked' => 'Expédition réservée auprès du transporteur',
            'picked_up' => 'Colis pris en charge par le transporteur',
            'in_transit' => 'Colis en transit',
            'out_for_delivery' => 'Colis en cours de livraison',
            'delivered' => 'Colis livré avec succès',
            'returned' => 'Colis retourné à l\'expéditeur',
            'failed' => 'Tentative de livraison échouée',
        ];

        $shipment->update([
            'status' => in_array($status, ['draft', 'booked', 'picked_up', 'in_transit', 'delivered', 'returned', 'failed']) ? $status : $shipment->status,
            'shipped_at' => $status === 'picked_up' ? now() : $shipment->shipped_at,
            'delivered_at' => $status === 'delivered' ? now() : $shipment->delivered_at,
        ]);

        /** @var ShipmentEvent $event */
        $event = ShipmentEvent::create([
            'shipment_id' => $shipment->id,
            'status' => $status,
            'location' => $location ?: 'En route',
            'description' => $statusDescriptions[$status] ?? 'Mise à jour du statut',
            'occurred_at' => now(),
        ]);

        return $event;
    }

    /**
     * Generate a unique shipment reference.
     */
    private function generateReference(): string
    {
        return 'SHP-'.strtoupper(uniqid());
    }

    /**
     * Generate a mock tracking number for a carrier.
     */
    private function generateTrackingNumber(int $carrierId): string
    {
        $carrier = Carrier::find($carrierId);
        $prefix = match ($carrier?->code) {
            'dhl' => 'JD',
            'fedex' => 'FX',
            'ups' => '1Z',
            'colissimo' => 'CP',
            'chronopost' => 'XK',
            'gls' => 'GL',
            default => 'SH',
        };

        return $prefix.strtoupper(bin2hex(random_bytes(8)));
    }

    /**
     * Generate mock tracking events for display purposes.
     */
    private function generateMockTrackingEvents(Shipment $shipment): void
    {
        $statusFlow = ['booked', 'picked_up', 'in_transit', 'delivered'];
        $locations = ['Paris CDG', 'Lyon Hub', 'Marseille', 'Entrepôt destination'];

        $currentIndex = array_search($shipment->status, $statusFlow);
        if ($currentIndex === false) {
            $currentIndex = 0;
        }

        for ($i = 0; $i <= $currentIndex; $i++) {
            ShipmentEvent::create([
                'shipment_id' => $shipment->id,
                'status' => $statusFlow[$i],
                'location' => $locations[$i] ?? 'En route',
                'description' => 'Événement simulé - '.$statusFlow[$i],
                'occurred_at' => now()->subDays((int) $currentIndex - $i),
            ]);
        }
    }
}
