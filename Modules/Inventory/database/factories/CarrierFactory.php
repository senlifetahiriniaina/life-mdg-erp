<?php

declare(strict_types=1);

namespace Modules\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Models\Carrier;

/** @extends Factory<Carrier> */
class CarrierFactory extends Factory
{
    protected $model = Carrier::class;

    /** @var array<int, array<string, string>> */
    private static array $carriers = [
        ['name' => 'DHL Express', 'code' => 'dhl', 'tracking_url_template' => 'https://www.dhl.com/fr-fr/home/tracking/tracking-freight.html?submit=1&tracking-id={tracking_number}'],
        ['name' => 'FedEx', 'code' => 'fedex', 'tracking_url_template' => 'https://www.fedex.com/apps/fedextrack/?action=track&trackingnumber={tracking_number}'],
        ['name' => 'UPS', 'code' => 'ups', 'tracking_url_template' => 'https://www.ups.com/track?tracknum={tracking_number}'],
        ['name' => 'Colissimo', 'code' => 'colissimo', 'tracking_url_template' => 'https://www.laposte.fr/outils/suivre-vos-envois?code={tracking_number}'],
        ['name' => 'Chronopost', 'code' => 'chronopost', 'tracking_url_template' => 'https://www.chronopost.fr/tracking-no-cms/suivi-page?listeNumerosLT={tracking_number}'],
        ['name' => 'GLS', 'code' => 'gls', 'tracking_url_template' => 'https://gls-group.com/track/{tracking_number}'],
    ];

    public function definition(): array
    {
        $carrier = fake()->unique()->randomElement(self::$carriers);

        return [
            'name' => $carrier['name'],
            'code' => $carrier['code'],
            'tracking_url_template' => $carrier['tracking_url_template'],
            'api_key' => fake()->optional()->sha256(),
            'active' => true,
            'settings' => null,
        ];
    }

    public function dhl(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'DHL Express',
            'code' => 'dhl',
            'tracking_url_template' => 'https://www.dhl.com/fr-fr/home/tracking/tracking-freight.html?submit=1&tracking-id={tracking_number}',
        ]);
    }

    public function fedex(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'FedEx',
            'code' => 'fedex',
            'tracking_url_template' => 'https://www.fedex.com/apps/fedextrack/?action=track&trackingnumber={tracking_number}',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }
}
