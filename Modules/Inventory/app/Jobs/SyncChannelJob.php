<?php

declare(strict_types=1);

namespace Modules\Inventory\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Inventory\Models\MarketplaceChannel;
use Modules\Inventory\Services\Marketplace\ChannelSyncService;

class SyncChannelJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int    $channelId,
        public string $type
    ) {}

    public function handle(ChannelSyncService $service): void
    {
        $channel = MarketplaceChannel::find($this->channelId);

        if (! $channel) {
            return;
        }

        $config    = $channel->config ?? [];
        $connector = $service->makeConnector($this->type, $config);

        $products = $connector->listProducts();
        $synced   = 0;
        $failed   = 0;

        foreach ($products as $product) {
            $sku = $product['sku'] ?? '';
            $qty = (int) ($product['quantity'] ?? 0);

            if (empty($sku)) {
                continue;
            }

            $result = $connector->syncInventory($sku, $qty);
            $result ? $synced++ : $failed++;
        }

        $channel->last_synced_at = now();
        $channel->sync_stats     = [
            'synced_at'        => now()->toIso8601String(),
            'products_synced'  => $synced,
            'products_failed'  => $failed,
            'total_products'   => count($products),
        ];
        $channel->save();
    }
}
