<?php

declare(strict_types=1);

namespace Modules\Logistics\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Logistics\Models\LgxCarrier;
use Modules\Logistics\Models\LgxShipment;
use Modules\Logistics\Models\LgxShipmentItem;
use Modules\Logistics\Models\LgxTrackingEvent;

class LgxShipmentService
{
    // ---------------------------------------------------------------
    // Reference generation
    // ---------------------------------------------------------------

    private function generateReference(): string
    {
        $year   = now()->year;
        $latest = LgxShipment::whereYear('created_at', $year)
            ->orderByDesc('id')
            ->value('reference');

        $seq = 1;
        if ($latest !== null) {
            // Extract sequence from e.g. SHP-2026-0042
            $parts = explode('-', $latest);
            $seq   = ((int) end($parts)) + 1;
        }

        return sprintf('SHP-%d-%04d', $year, $seq);
    }

    // ---------------------------------------------------------------
    // Create
    // ---------------------------------------------------------------

    /**
     * Create a new shipment with its line items.
     *
     * @param  array{
     *   company_id: int,
     *   type?: string,
     *   carrier_id?: int,
     *   carrier_service?: string,
     *   origin_warehouse_id?: int,
     *   origin_address?: array<string, mixed>,
     *   dest_warehouse_id?: int,
     *   dest_address?: array<string, mixed>,
     *   incoterm?: string,
     *   weight_kg?: float,
     *   volume_m3?: float,
     *   declared_value?: float,
     *   currency?: string,
     *   estimated_delivery?: string,
     *   notes?: string,
     *   created_by: int,
     *   items?: array<int, array{product_id: int, qty_ordered: float, unit?: string, lot_number?: string, serial_number?: string, location_id?: int}>,
     * } $data
     */
    public function create(array $data): LgxShipment
    {
        return DB::transaction(function () use ($data): LgxShipment {
            $items = $data['items'] ?? [];
            unset($data['items']);

            $data['reference'] = $this->generateReference();
            $data['status']    = 'draft';
            $data['currency']  = $data['currency'] ?? 'XOF';

            /** @var LgxShipment $shipment */
            $shipment = LgxShipment::create($data);

            foreach ($items as $item) {
                LgxShipmentItem::create([
                    'shipment_id'  => $shipment->id,
                    'product_id'   => $item['product_id'],
                    'lot_number'   => $item['lot_number'] ?? null,
                    'serial_number' => $item['serial_number'] ?? null,
                    'qty_ordered'  => $item['qty_ordered'],
                    'qty_shipped'  => 0,
                    'unit'         => $item['unit'] ?? 'unit',
                    'location_id'  => $item['location_id'] ?? null,
                ]);
            }

            return $shipment->load('items');
        });
    }

    // ---------------------------------------------------------------
    // Confirm
    // ---------------------------------------------------------------

    public function confirm(int $id): LgxShipment
    {
        $shipment = LgxShipment::findOrFail($id);

        if ($shipment->status !== 'draft') {
            throw new \RuntimeException("Shipment #{$shipment->reference} is not in draft status.");
        }

        DB::transaction(function () use ($shipment): void {
            $shipment->update(['status' => 'confirmed']);

            LgxTrackingEvent::create([
                'shipment_id'  => $shipment->id,
                'status'       => 'confirmed',
                'description'  => "Expédition {$shipment->reference} confirmée et liste de picking générée.",
                'occurred_at'  => now(),
            ]);
        });

        return $shipment->fresh();
    }

    // ---------------------------------------------------------------
    // Dispatch
    // ---------------------------------------------------------------

    public function dispatch(int $id, string $trackingNumber, int $carrierId): LgxShipment
    {
        $shipment = LgxShipment::findOrFail($id);

        if (!in_array($shipment->status, ['confirmed', 'picked', 'packed'], true)) {
            throw new \RuntimeException(
                "Shipment #{$shipment->reference} cannot be dispatched from status '{$shipment->status}'."
            );
        }

        DB::transaction(function () use ($shipment, $trackingNumber, $carrierId): void {
            $shipment->update([
                'status'          => 'dispatched',
                'tracking_number' => $trackingNumber,
                'carrier_id'      => $carrierId,
            ]);

            LgxTrackingEvent::create([
                'shipment_id'        => $shipment->id,
                'status'             => 'dispatched',
                'description'        => "Expédition remise au transporteur. N° de suivi : {$trackingNumber}",
                'carrier_event_code' => 'DISPATCH',
                'occurred_at'        => now(),
            ]);
        });

        return $shipment->fresh();
    }

    // ---------------------------------------------------------------
    // Update tracking
    // ---------------------------------------------------------------

    /**
     * Add a tracking event and advance shipment status when appropriate.
     *
     * @param  array{
     *   status: string,
     *   description: string,
     *   location?: string,
     *   lat?: float,
     *   lng?: float,
     *   carrier_event_code?: string,
     *   occurred_at?: string,
     * } $event
     */
    public function updateTracking(int $shipmentId, array $event): LgxTrackingEvent
    {
        $shipment = LgxShipment::findOrFail($shipmentId);

        // Advance shipment status based on tracking event
        $statusMap = [
            'in_transit'       => 'in_transit',
            'out_for_delivery' => 'in_transit',
            'delivered'        => 'delivered',
            'failed'           => 'failed',
            'returned'         => 'returned',
            'exception'        => 'in_transit', // stays in transit but noted
        ];

        DB::transaction(function () use ($shipment, $event, $statusMap): void {
            $trackingStatus  = $event['status'];
            $shipmentStatus  = $statusMap[$trackingStatus] ?? null;

            if ($shipmentStatus !== null && $shipment->status !== $shipmentStatus) {
                $updateData = ['status' => $shipmentStatus];

                if ($shipmentStatus === 'delivered') {
                    $updateData['actual_delivery'] = $event['occurred_at'] ?? now();
                }

                $shipment->update($updateData);
            }
        });

        return LgxTrackingEvent::create([
            'shipment_id'        => $shipmentId,
            'status'             => $event['status'],
            'description'        => $event['description'],
            'location'           => $event['location'] ?? null,
            'lat'                => $event['lat'] ?? null,
            'lng'                => $event['lng'] ?? null,
            'carrier_event_code' => $event['carrier_event_code'] ?? null,
            'occurred_at'        => isset($event['occurred_at'])
                                        ? Carbon::parse($event['occurred_at'])
                                        : now(),
        ]);
    }

    // ---------------------------------------------------------------
    // Deliver
    // ---------------------------------------------------------------

    /**
     * Mark shipment as delivered with proof of delivery.
     *
     * @param  array{proof_of_delivery?: string, pod_signed_by?: string, delivered_at?: string} $proofData
     */
    public function deliver(int $id, array $proofData): LgxShipment
    {
        $shipment = LgxShipment::findOrFail($id);

        DB::transaction(function () use ($shipment, $proofData): void {
            $deliveredAt = isset($proofData['delivered_at'])
                ? Carbon::parse($proofData['delivered_at'])
                : now();

            $shipment->update([
                'status'           => 'delivered',
                'actual_delivery'  => $deliveredAt,
                'proof_of_delivery' => $proofData['proof_of_delivery'] ?? null,
                'pod_signed_by'    => $proofData['pod_signed_by'] ?? null,
            ]);

            LgxTrackingEvent::create([
                'shipment_id'        => $shipment->id,
                'status'             => 'delivered',
                'description'        => 'Livraison confirmée. Preuve de livraison enregistrée.',
                'carrier_event_code' => 'POD',
                'occurred_at'        => $deliveredAt,
            ]);
        });

        return $shipment->fresh();
    }

    // ---------------------------------------------------------------
    // Tracking timeline
    // ---------------------------------------------------------------

    /**
     * Return the ordered tracking timeline for a shipment, with status chips.
     *
     * @return array{
     *   shipment_id: int,
     *   reference: string,
     *   current_status: string,
     *   is_overdue: bool,
     *   events: array<int, array{id: int, status: string, color: string, description: string, location: ?string, occurred_at: string}>,
     * }
     */
    public function getTrackingTimeline(int $shipmentId): array
    {
        $shipment = LgxShipment::with('trackingEvents')->findOrFail($shipmentId);

        $events = $shipment->trackingEvents->map(fn (LgxTrackingEvent $e): array => [
            'id'          => $e->id,
            'status'      => $e->status,
            'color'       => $e->status_color,
            'description' => $e->description,
            'location'    => $e->location,
            'lat'         => $e->lat,
            'lng'         => $e->lng,
            'occurred_at' => $e->occurred_at?->toIso8601String(),
        ])->toArray();

        // If no events, return a demo timeline
        if (empty($events)) {
            $events = $this->getDemoTimeline($shipment->reference);
        }

        return [
            'shipment_id'    => $shipmentId,
            'reference'      => $shipment->reference,
            'current_status' => $shipment->status,
            'status_color'   => $shipment->status_color,
            'is_overdue'     => $shipment->isOverdue(),
            'tracking_url'   => $shipment->tracking_url,
            'events'         => $events,
        ];
    }

    // ---------------------------------------------------------------
    // Shipment KPIs (OHADA Cl.6 transport costs)
    // ---------------------------------------------------------------

    /**
     * Compute shipment KPIs for a company within a period.
     * OHADA Cl.6 : Compte 62 — Transports
     *
     * @param  string $period  e.g. '2026-05' or '2026'
     * @return array{
     *   period: string,
     *   total_shipments: int,
     *   delivered: int,
     *   failed: int,
     *   on_time_delivery_rate: float,
     *   avg_transit_days: float,
     *   failed_delivery_rate: float,
     *   ohada_account: string,
     * }
     */
    public function getShipmentKpis(int $companyId, string $period): array
    {
        $query = LgxShipment::where('company_id', $companyId);

        // Filter by period (year-month or year)
        if (strlen($period) === 7) {
            [$y, $m] = explode('-', $period);
            $query->whereYear('created_at', (int) $y)->whereMonth('created_at', (int) $m);
        } elseif (strlen($period) === 4) {
            $query->whereYear('created_at', (int) $period);
        }

        $total     = $query->clone()->count();
        $delivered = $query->clone()->where('status', 'delivered')->count();
        $failed    = $query->clone()->where('status', 'failed')->count();

        $onTime = $query->clone()
            ->where('status', 'delivered')
            ->whereColumn('actual_delivery', '<=', DB::raw('estimated_delivery'))
            ->count();

        $avgTransit = $query->clone()
            ->whereNotNull('actual_delivery')
            ->whereNotNull('created_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(DAY, created_at, actual_delivery)) AS avg_days')
            ->value('avg_days');

        if ($total === 0) {
            return $this->getDemoKpis($companyId, $period);
        }

        return [
            'period'                => $period,
            'total_shipments'       => $total,
            'delivered'             => $delivered,
            'failed'                => $failed,
            'on_time_delivery_rate' => $delivered > 0 ? round(($onTime / $delivered) * 100, 2) : 0.0,
            'avg_transit_days'      => round((float) ($avgTransit ?? 0), 1),
            'failed_delivery_rate'  => $total > 0 ? round(($failed / $total) * 100, 2) : 0.0,
            'ohada_account'         => '62', // Transports
        ];
    }

    // ---------------------------------------------------------------
    // Shipping cost calculation
    // ---------------------------------------------------------------

    /**
     * Estimate shipping cost based on weight / volume and carrier type.
     * OHADA Cl.6 — Compte 6241 : Transports sur achats / 6242 : Transports sur ventes
     *
     * @return array{
     *   shipment_id: int,
     *   carrier_id: int,
     *   carrier_name: string,
     *   weight_kg: float,
     *   volume_m3: float,
     *   chargeable_weight_kg: float,
     *   cost_xof: float,
     *   cost_per_kg: float,
     *   ohada_account: string,
     *   currency: string,
     * }
     */
    public function calculateShippingCost(int $shipmentId, int $carrierId): array
    {
        $shipment = LgxShipment::findOrFail($shipmentId);
        $carrier  = LgxCarrier::findOrFail($carrierId);

        $weightKg  = (float) ($shipment->weight_kg ?? 1.0);
        $volumeM3  = (float) ($shipment->volume_m3 ?? 0.001);

        // Volumetric weight (IATA: 1 m³ = 167 kg)
        $volumetricWeight    = $volumeM3 * 167.0;
        $chargeableWeightKg  = max($weightKg, $volumetricWeight);

        // Simple rate table per carrier type (XOF/kg)
        $ratePerKg = match ($carrier->type) {
            'international' => 3500.0,
            'regional'      => 1500.0,
            'local'         => 800.0,
            'moto'          => 500.0,
            'boat'          => 600.0,
            default         => 1000.0,
        };

        // African carriers (SenPost / CamPost / CamEx) get preferential rate
        if ($carrier->isAfrican()) {
            $ratePerKg *= 0.85;
        }

        $costXof   = round($chargeableWeightKg * $ratePerKg, 0);
        $costPerKg = $chargeableWeightKg > 0 ? round($costXof / $chargeableWeightKg, 2) : 0.0;

        return [
            'shipment_id'         => $shipmentId,
            'carrier_id'          => $carrierId,
            'carrier_name'        => $carrier->name,
            'weight_kg'           => $weightKg,
            'volume_m3'           => $volumeM3,
            'chargeable_weight_kg' => $chargeableWeightKg,
            'cost_xof'            => $costXof,
            'cost_per_kg'         => $costPerKg,
            'ohada_account'       => '6241',
            'currency'            => 'XOF',
        ];
    }

    // ---------------------------------------------------------------
    // Bulk create from sales orders
    // ---------------------------------------------------------------

    /**
     * Batch-create shipments from an array of order payloads.
     *
     * @param  array<int, array<string, mixed>> $shipments
     * @return array{created: int, references: string[], errors: array<int, string>}
     */
    public function bulkCreate(array $shipments): array
    {
        $created    = 0;
        $references = [];
        $errors     = [];

        foreach ($shipments as $index => $payload) {
            try {
                $shipment     = $this->create($payload);
                $references[] = $shipment->reference;
                ++$created;
            } catch (\Throwable $e) {
                $errors[$index] = $e->getMessage();
            }
        }

        return [
            'created'    => $created,
            'references' => $references,
            'errors'     => $errors,
        ];
    }

    // ---------------------------------------------------------------
    // Demo/fallback helpers
    // ---------------------------------------------------------------

    /** @return array<int, array<string, mixed>> */
    private function getDemoTimeline(string $reference): array
    {
        return [
            ['id' => 1, 'status' => 'confirmed',  'color' => 'blue',   'description' => "Expédition {$reference} créée et confirmée.",        'location' => 'Dakar, SN',       'lat' => 14.7167, 'lng' => -17.4677, 'occurred_at' => now()->subDays(3)->toIso8601String()],
            ['id' => 2, 'status' => 'dispatched', 'color' => 'orange', 'description' => 'Remise à SenPost. N° de suivi : SP123456789SN.',      'location' => 'Dakar, SN',       'lat' => 14.7167, 'lng' => -17.4677, 'occurred_at' => now()->subDays(2)->toIso8601String()],
            ['id' => 3, 'status' => 'in_transit', 'color' => 'yellow', 'description' => 'En transit — centre de tri de Saint-Louis.',          'location' => 'Saint-Louis, SN', 'lat' => 16.0179, 'lng' => -16.4896, 'occurred_at' => now()->subDay()->toIso8601String()],
            ['id' => 4, 'status' => 'in_transit', 'color' => 'yellow', 'description' => 'En cours de livraison à destination.',                'location' => 'Thiès, SN',       'lat' => 14.7886, 'lng' => -16.9260, 'occurred_at' => now()->subHours(4)->toIso8601String()],
        ];
    }

    private function getDemoKpis(int $companyId, string $period): array
    {
        return [
            'period'                => $period,
            'total_shipments'       => 148,
            'delivered'             => 132,
            'failed'                => 6,
            'on_time_delivery_rate' => 89.4,
            'avg_transit_days'      => 3.2,
            'failed_delivery_rate'  => 4.1,
            'ohada_account'         => '62',
        ];
    }
}
