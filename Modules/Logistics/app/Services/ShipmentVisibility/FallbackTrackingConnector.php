<?php

declare(strict_types=1);

namespace Modules\Logistics\Services\ShipmentVisibility;

/**
 * Generic fallback tracking connector.
 * Maps carrier slug → tracking URL template.
 */
class FallbackTrackingConnector
{
    private const CARRIER_TEMPLATES = [
        'dhl'           => 'https://www.dhl.com/en/express/tracking.html?AWB={number}',
        'fedex'         => 'https://www.fedex.com/fedextrack/?tracknumbers={number}',
        'ups'           => 'https://www.ups.com/track?tracknum={number}',
        'maersk'        => 'https://www.maersk.com/tracking/{number}',
        'msc'           => 'https://www.msc.com/track-a-shipment?trackingNumber={number}',
        'cma-cgm'       => 'https://www.cma-cgm.com/ebusiness/tracking/search?number={number}',
        'evergreen'     => 'https://www.evergreen-line.com/static/html/cargotracking.html?number={number}',
        'senpost'       => 'https://www.laposte.sn/suivi?number={number}',
        'bolloré'       => 'https://www.bollore-logistics.com/en/tracking/{number}',
        'chronopost'    => 'https://www.chronopost.fr/tracking-no-cms/{number}',
        'default'       => 'https://track.widehalo.com/{number}',
    ];

    public function getTrackingUrl(string $carrierSlug, string $trackingNumber): string
    {
        $template = self::CARRIER_TEMPLATES[strtolower($carrierSlug)]
            ?? self::CARRIER_TEMPLATES['default'];

        return str_replace('{number}', urlencode($trackingNumber), $template);
    }

    public function getFallbackVisibility(string $trackingNumber, string $carrierSlug): array
    {
        return [
            'mode'             => 'unknown',
            'carrier'          => $carrierSlug,
            'tracking_number'  => $trackingNumber,
            'tracking_url'     => $this->getTrackingUrl($carrierSlug, $trackingNumber),
            'status'           => 'in_transit',
            'current_position' => ['lat' => null, 'lng' => null],
            'eta'              => null,
            'events'           => [],
            'fallback'         => true,
        ];
    }
}
