<?php

declare(strict_types=1);

namespace Modules\Inventory\Services\Marketplace;

use Illuminate\Support\Collection;
use Modules\Inventory\Jobs\SyncChannelJob;
use Modules\Inventory\Models\MarketplaceChannel;

class ChannelSyncService
{
    /** @var array<string, MarketplaceConnectorInterface> */
    private array $connectors = [];

    public function registerConnector(string $type, MarketplaceConnectorInterface $connector): void
    {
        $this->connectors[$type] = $connector;
    }

    public function getConnector(string $type): ?MarketplaceConnectorInterface
    {
        return $this->connectors[$type] ?? null;
    }

    public function makeConnector(string $type, array $config): MarketplaceConnectorInterface
    {
        return match ($type) {
            'amazon' => new AmazonSpApiConnector($config),
            'ebay'   => new EbayConnector($config),
            default  => throw new \InvalidArgumentException("Unknown connector type: {$type}"),
        };
    }

    public function syncAllChannels(int $tenantId): array
    {
        $channels = $this->listChannels($tenantId);
        $results  = [];

        foreach ($channels as $channel) {
            SyncChannelJob::dispatch($channel->id, $channel->type);
            $results[] = [
                'channel_id' => $channel->id,
                'type'       => $channel->type,
                'name'       => $channel->name,
                'queued'     => true,
            ];
        }

        return $results;
    }

    public function resolveConflict(string $sku, int $erpQty, int $channelQty): int
    {
        // ERP wins: always return ERP quantity
        return $erpQty;
    }

    public function listChannels(int $tenantId): Collection
    {
        return MarketplaceChannel::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->get();
    }
}
