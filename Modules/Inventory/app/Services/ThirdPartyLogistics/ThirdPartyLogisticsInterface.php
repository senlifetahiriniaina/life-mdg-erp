<?php

declare(strict_types=1);

namespace Modules\Inventory\Services\ThirdPartyLogistics;

interface ThirdPartyLogisticsInterface
{
    /**
     * Create a fulfillment order at the 3PL.
     *
     * @param  array $order  {order_id, items[{sku, quantity}], ship_to{name, address, city, state, zip, country}, shipping_method}
     * @return array {reference_id, status, estimated_ship_date, tracking_number|null}
     */
    public function createFulfillmentOrder(array $order): array;

    /**
     * Get the status of a fulfillment order by 3PL reference ID.
     *
     * @return array {reference_id, status, tracking_number|null, carrier|null, shipped_at|null, delivered_at|null, items[]}
     */
    public function getOrderStatus(string $referenceId): array;

    /**
     * Cancel a fulfillment order at the 3PL.
     */
    public function cancelOrder(string $referenceId): bool;

    /**
     * Get current inventory levels from the 3PL warehouse.
     *
     * @return array [{sku, quantity_on_hand, quantity_reserved, quantity_available}]
     */
    public function getInventoryLevels(): array;

    /**
     * Update inventory quantity for a SKU at the 3PL.
     */
    public function updateInventory(string $sku, int $qty): bool;

    /**
     * Returns true when running against a sandbox/mock environment.
     */
    public function isSandbox(): bool;
}
