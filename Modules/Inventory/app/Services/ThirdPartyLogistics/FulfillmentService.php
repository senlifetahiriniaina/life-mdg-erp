<?php

declare(strict_types=1);

namespace Modules\Inventory\Services\ThirdPartyLogistics;

use RuntimeException;

/**
 * 3PL FulfillmentService — manages connectors and routes orders.
 */
class FulfillmentService
{
    /** @var array<string, ThirdPartyLogisticsInterface> */
    private array $connectors = [];

    public function __construct()
    {
        // Auto-register connectors from config when keys are present
        $this->bootDefaultConnectors();
    }

    // ─── Connector Registry ───────────────────────────────────────────────

    public function registerConnector(string $name, ThirdPartyLogisticsInterface $connector): void
    {
        $this->connectors[$name] = $connector;
    }

    public function getConnector(string $name): ThirdPartyLogisticsInterface
    {
        if (!isset($this->connectors[$name])) {
            throw new RuntimeException("3PL connector '{$name}' is not registered.");
        }
        return $this->connectors[$name];
    }

    /**
     * @return string[]
     */
    public function availableConnectors(): array
    {
        return array_keys($this->connectors);
    }

    // ─── Fulfillment Operations ───────────────────────────────────────────

    /**
     * Route a fulfillment order to the specified connector.
     *
     * @param  int|string $orderId      Internal order ID
     * @param  string     $connectorName  'shipbob'|'shipmonk'|'fba'|…
     * @param  array      $orderData    Order payload including items and ship_to
     */
    public function routeFulfillment(int|string $orderId, string $connectorName, array $orderData = []): array
    {
        $connector = $this->getConnector($connectorName);

        $order = array_merge($orderData, ['order_id' => $orderId]);

        $result = $connector->createFulfillmentOrder($order);

        return array_merge($result, [
            'connector'  => $connectorName,
            'order_id'   => $orderId,
            'routed_at'  => now()->toIso8601String(),
        ]);
    }

    /**
     * Track fulfillment status for a 3PL reference ID.
     */
    public function trackFulfillment(string $referenceId, string $connectorName): array
    {
        return $this->getConnector($connectorName)->getOrderStatus($referenceId);
    }

    /**
     * Sync inventory from a specific fulfiller into a normalized structure.
     *
     * @return array [{sku, quantity_on_hand, quantity_reserved, quantity_available, connector}]
     */
    public function syncInventoryFromFulfiller(string $connectorName): array
    {
        $levels = $this->getConnector($connectorName)->getInventoryLevels();

        return array_map(fn ($item) => array_merge($item, ['connector' => $connectorName]), $levels);
    }

    // ─── Private Helpers ──────────────────────────────────────────────────

    private function bootDefaultConnectors(): void
    {
        $this->registerConnector('shipbob', new ShipBobConnector(
            config('services.shipbob', [])
        ));

        $this->registerConnector('shipmonk', new ShipMonkConnector(
            config('services.shipmonk', [])
        ));

        $this->registerConnector('fba', new FulfillmentByAmazonConnector(
            config('services.fba', [])
        ));
    }
}
