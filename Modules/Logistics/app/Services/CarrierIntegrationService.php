<?php

declare(strict_types=1);

namespace Modules\Logistics\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Logistics\Models\Carrier;
use Modules\Logistics\Models\CarrierRateCard;
use Modules\Logistics\Models\Shipment;
use Modules\Logistics\Models\TrackingEvent;

/**
 * Carrier integration service.
 *
 * Africa First: SenPost, CamPost, DHL Africa, Chronopost, Bollore Logistics.
 * Graceful fallback to manual/DB tracking when carrier credentials absent.
 */
class CarrierIntegrationService
{
    private const CARRIER_SENPOST    = 'senpost';
    private const CARRIER_CAMPOST    = 'campost';
    private const CARRIER_DHL        = 'dhl';
    private const CARRIER_CHRONOPOST = 'chronopost';
    private const CARRIER_BOLLORE    = 'bollore';
    private const CARRIER_GENERIC    = 'generic';

    private const CARRIER_COVERAGE = [
        self::CARRIER_SENPOST => ['SN', 'ML', 'BF', 'GN', 'GW', 'CV', 'MR', 'GM'],
        self::CARRIER_CAMPOST => ['CM', 'CF', 'CG', 'GA', 'TD', 'GQ', 'CD'],
        self::CARRIER_DHL => [
            'DZ', 'AO', 'BJ', 'BW', 'BF', 'BI', 'CM', 'CV', 'CF', 'TD',
            'KM', 'CG', 'CD', 'CI', 'DJ', 'EG', 'ER', 'ET', 'GA', 'GM',
            'GH', 'GN', 'GW', 'KE', 'LS', 'LR', 'LY', 'MG', 'MW', 'ML',
            'MR', 'MU', 'MA', 'MZ', 'NA', 'NE', 'NG', 'RW', 'ST', 'SN',
            'SC', 'SL', 'SO', 'ZA', 'SS', 'SD', 'SZ', 'TZ', 'TG', 'TN',
            'UG', 'ZM', 'ZW', 'FR', 'DE', 'GB', 'CN', 'US', 'IN',
        ],
        self::CARRIER_CHRONOPOST => ['FR', 'BE', 'SN', 'CI', 'MG', 'CM', 'ML', 'BF', 'TG', 'BJ'],
        self::CARRIER_BOLLORE    => ['SN', 'CI', 'CM', 'NG', 'GH', 'GA', 'CG', 'CD', 'MG', 'TZ', 'KE'],
    ];

    /**
     * Unified tracking response regardless of carrier type.
     *
     * @return array<string, mixed>
     */
    public function trackShipment(int $carrierId, string $trackingNumber): array
    {
        $carrier = Carrier::findOrFail($carrierId);
        $type    = strtolower($carrier->type ?? self::CARRIER_GENERIC);

        $result = match (true) {
            str_contains($type, 'dhl')        => $this->trackDhl($trackingNumber),
            str_contains($type, 'chronopost') => $this->trackChronopost($trackingNumber),
            default                            => null,
        };

        if ($result !== null) {
            return $result;
        }

        return $this->trackFromDatabase($carrierId, $trackingNumber, $carrier->name);
    }

    private function trackDhl(string $trackingNumber): ?array
    {
        $apiKey = config('services.dhl.api_key');

        if (! $apiKey) {
            return null;
        }

        try {
            $response = Http::withHeaders(['DHL-API-Key' => $apiKey])
                ->timeout(8)
                ->get('https://api-eu.dhl.com/track/shipments', [
                    'trackingNumber' => $trackingNumber,
                    'language'       => 'fr',
                ]);

            if (! $response->successful()) {
                return null;
            }

            $data   = $response->json('shipments.0', []);
            $events = collect($data['events'] ?? [])->map(fn($e) => [
                'status'      => $e['description'] ?? 'unknown',
                'location'    => $e['location']['address']['addressLocality'] ?? '',
                'timestamp'   => $e['timestamp'] ?? '',
                'description' => $e['description'] ?? '',
            ])->toArray();

            return [
                'tracking_number'    => $trackingNumber,
                'carrier'            => 'DHL',
                'status'             => $data['status']['description'] ?? 'unknown',
                'location'           => $data['status']['location']['address']['addressLocality'] ?? '',
                'timestamp'          => $data['status']['timestamp'] ?? now()->toIso8601String(),
                'events'             => $events,
                'estimated_delivery' => $data['estimatedTimeOfDelivery'] ?? null,
                'source'             => 'dhl_api',
            ];
        } catch (\Throwable $e) {
            Log::warning("CarrierIntegration: DHL track failed for {$trackingNumber}", ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function trackChronopost(string $trackingNumber): ?array
    {
        if (! config('services.chronopost.api_key')) {
            return null;
        }

        return [
            'tracking_number'    => $trackingNumber,
            'carrier'            => 'Chronopost',
            'status'             => 'En transit',
            'location'           => 'Hub Dakar Yoff',
            'timestamp'          => now()->toIso8601String(),
            'events'             => [
                [
                    'status'      => 'Pris en charge',
                    'location'    => 'Dakar',
                    'timestamp'   => now()->subHours(12)->toIso8601String(),
                    'description' => 'Colis pris en charge par Chronopost',
                ],
                [
                    'status'      => 'En transit',
                    'location'    => 'Hub Dakar Yoff',
                    'timestamp'   => now()->toIso8601String(),
                    'description' => 'En cours de traitement au hub',
                ],
            ],
            'estimated_delivery' => now()->addDays(3)->toDateString(),
            'source'             => 'chronopost_mock',
        ];
    }

    private function trackFromDatabase(int $carrierId, string $trackingNumber, string $carrierName): array
    {
        $events = TrackingEvent::where('tracking_number', $trackingNumber)
            ->orderByDesc('recorded_at')
            ->take(20)
            ->get();

        $latest = $events->first();

        return [
            'tracking_number'    => $trackingNumber,
            'carrier'            => $carrierName,
            'status'             => $latest?->status_detail ?? 'Aucun evenement enregistre',
            'location'           => $latest?->location ?? '',
            'timestamp'          => $latest?->recorded_at?->toIso8601String() ?? now()->toIso8601String(),
            'events'             => $events->map(fn(TrackingEvent $e) => [
                'status'      => $e->status_detail ?? $e->event_type,
                'location'    => $e->location ?? '',
                'timestamp'   => $e->recorded_at?->toIso8601String() ?? '',
                'description' => $e->notes ?? $e->status_detail ?? '',
            ])->toArray(),
            'estimated_delivery' => null,
            'source'             => 'manual',
        ];
    }

    /**
     * Rate lookup from carrier rate card.
     *
     * @param array{origin_country: string, dest_country: string, weight_kg: float, service_type?: string} $shipmentSpec
     * @return array<string, mixed>|null
     */
    public function getRate(int $carrierId, array $shipmentSpec): ?array
    {
        $weightKg    = (float) ($shipmentSpec['weight_kg'] ?? 1.0);
        $serviceType = $shipmentSpec['service_type'] ?? 'standard';

        $rateCard = CarrierRateCard::where('carrier_id', $carrierId)
            ->where('origin_country', strtoupper($shipmentSpec['origin_country'] ?? ''))
            ->where('dest_country', strtoupper($shipmentSpec['dest_country'] ?? ''))
            ->where('service_type', $serviceType)
            ->where('is_active', true)
            ->where('weight_min_kg', '<=', $weightKg)
            ->where('weight_max_kg', '>=', $weightKg)
            ->first();

        if ($rateCard === null) {
            $rateCard = CarrierRateCard::where('carrier_id', $carrierId)
                ->where('origin_country', strtoupper($shipmentSpec['origin_country'] ?? ''))
                ->where('dest_country', strtoupper($shipmentSpec['dest_country'] ?? ''))
                ->where('is_active', true)
                ->where('weight_min_kg', '<=', $weightKg)
                ->where('weight_max_kg', '>=', $weightKg)
                ->first();
        }

        if ($rateCard === null) {
            return null;
        }

        return [
            'carrier_id'       => $carrierId,
            'rate'             => $rateCard->calculateRate($weightKg),
            'currency'         => $rateCard->currency,
            'transit_days_min' => $rateCard->transit_days_min,
            'transit_days_max' => $rateCard->transit_days_max,
            'service_type'     => $rateCard->service_type,
        ];
    }

    /**
     * Book a shipment. DHL live API if credentials present; mock for all others.
     *
     * @return array{booking_reference: string, status: string, carrier: string, source: string}
     */
    public function bookShipment(int $carrierId, int $shipmentId): array
    {
        $carrier  = Carrier::findOrFail($carrierId);
        $shipment = Shipment::findOrFail($shipmentId);
        $type     = strtolower($carrier->type ?? self::CARRIER_GENERIC);

        if (str_contains($type, 'dhl') && config('services.dhl.api_key')) {
            return $this->bookDhl($carrier, $shipment);
        }

        $ref = sprintf('%s-%s-%05d', strtoupper(substr($carrier->name, 0, 3)), now()->format('ymd'), $shipmentId);
        $shipment->update(['tracking_number' => $ref]);

        return ['booking_reference' => $ref, 'status' => 'confirmed', 'carrier' => $carrier->name, 'source' => 'mock'];
    }

    private function bookDhl(Carrier $carrier, Shipment $shipment): array
    {
        try {
            $response = Http::withHeaders([
                'DHL-API-Key'  => config('services.dhl.api_key'),
                'content-type' => 'application/json',
            ])->timeout(15)->post('https://api-eu.dhl.com/express/v0/shipments', [
                'plannedShippingDateAndTime' => now()->addDay()->toIso8601String(),
                'pickup'   => ['isRequested' => false],
                'accounts' => [['typeCode' => 'shipper', 'number' => config('services.dhl.account')]],
                'content'  => [
                    'unitOfMeasurement' => 'metric',
                    // Chantier 32.23: read a `total_weight_kg` attribute
                    // that has never existed on Shipment (the real fillable
                    // column is `weight_kg`) — every real DHL booking has
                    // always sent a hardcoded 1kg regardless of the
                    // shipment's actual weight, confirmed via
                    // Schema::getColumnListing. Only reachable when a real
                    // services.dhl.api_key is configured, which it isn't in
                    // this environment, so this was dormant rather than
                    // actively breaking any test.
                    'packages'          => [['weight' => ['value' => $shipment->weight_kg ?? 1, 'unitOfMeasurement' => 'kg']]],
                ],
            ]);

            if ($response->successful()) {
                $ref = $response->json('shipmentTrackingNumber');
                $shipment->update(['tracking_number' => $ref]);

                return ['booking_reference' => $ref, 'status' => 'confirmed', 'carrier' => 'DHL', 'source' => 'dhl_api'];
            }
        } catch (\Throwable $e) {
            Log::warning('CarrierIntegration: DHL book failed', ['error' => $e->getMessage()]);
        }

        $ref = sprintf('DHL-%s-%05d', now()->format('ymd'), $shipment->id);
        $shipment->update(['tracking_number' => $ref]);

        return ['booking_reference' => $ref, 'status' => 'confirmed', 'carrier' => 'DHL', 'source' => 'mock'];
    }

    /**
     * Generate shipping label (base64 PDF stub or real carrier label).
     *
     * @return array{label_base64: string, format: string, tracking_number: string, shipment_ref: string}
     */
    public function generateLabel(int $shipmentId): array
    {
        $shipment = Shipment::findOrFail($shipmentId);
        $stubPdf  = base64_encode('%PDF-1.4 stub label shipment#' . $shipmentId);

        return [
            'label_base64'    => $stubPdf,
            'format'          => 'pdf',
            'tracking_number' => $shipment->tracking_number ?? 'N/A',
            'shipment_ref'    => $shipment->reference ?? '',
        ];
    }

    /**
     * ISO-alpha-2 country codes covered by a carrier.
     *
     * @return array<int, string>
     */
    public function getSupportedCountries(int $carrierId): array
    {
        $carrier = Carrier::findOrFail($carrierId);
        $type    = strtolower($carrier->type ?? self::CARRIER_GENERIC);

        foreach (self::CARRIER_COVERAGE as $key => $countries) {
            if (str_contains($type, $key)) {
                return $countries;
            }
        }

        return CarrierRateCard::where('carrier_id', $carrierId)
            ->where('is_active', true)
            ->distinct()
            ->pluck('dest_country')
            ->toArray();
    }
}
