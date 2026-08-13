<?php

declare(strict_types=1);

namespace Modules\Inventory\Services\Marketplace;

interface MarketplaceConnectorInterface
{
    public function listProducts(): array;

    public function syncInventory(string $sku, int $qty): bool;

    public function getOrders(): array;

    public function acknowledgeOrder(string $orderId): bool;

    public function getStatus(): array;
}
