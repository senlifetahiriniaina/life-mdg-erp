<?php

declare(strict_types=1);

namespace Modules\Inventory\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Modules\Inventory\Jobs\SyncChannelJob;
use Modules\Inventory\Services\Marketplace\AmazonSpApiConnector;
use Modules\Inventory\Services\Marketplace\ChannelSyncService;
use Modules\Inventory\Services\Marketplace\EbayConnector;
use Tests\TestCase;

class ChannelConnectorTest extends TestCase
{
    use RefreshDatabase;

    // ─── Amazon ────────────────────────────────────────────────────────

    public function test_can_register_amazon_connector(): void
    {
        $service   = new ChannelSyncService();
        $connector = new AmazonSpApiConnector([]);

        $service->registerConnector('amazon', $connector);

        $this->assertSame($connector, $service->getConnector('amazon'));
    }

    public function test_can_register_ebay_connector(): void
    {
        $service   = new ChannelSyncService();
        $connector = new EbayConnector([]);

        $service->registerConnector('ebay', $connector);

        $this->assertSame($connector, $service->getConnector('ebay'));
    }

    public function test_amazon_connector_returns_sandbox_mode_when_no_credentials(): void
    {
        $connector = new AmazonSpApiConnector([]);

        $this->assertTrue($connector->isSandbox());

        $status = $connector->getStatus();
        $this->assertSame('amazon', $status['type']);
        $this->assertTrue($status['sandbox_mode']);
        $this->assertFalse($status['connected']);
    }

    public function test_ebay_connector_returns_sandbox_mode_when_no_credentials(): void
    {
        $connector = new EbayConnector([]);

        $this->assertTrue($connector->isSandbox());

        $status = $connector->getStatus();
        $this->assertSame('ebay', $status['type']);
        $this->assertTrue($status['sandbox_mode']);
        $this->assertFalse($status['connected']);
    }

    public function test_channel_sync_service_resolves_conflict_erp_wins(): void
    {
        $service = new ChannelSyncService();

        $resolved = $service->resolveConflict('SKU-001', 50, 30);

        $this->assertSame(50, $resolved, 'ERP quantity should win conflict resolution');
    }

    public function test_can_list_products_from_amazon_sandbox(): void
    {
        $connector = new AmazonSpApiConnector([]);

        $products = $connector->listProducts();

        $this->assertIsArray($products);
        $this->assertNotEmpty($products);

        foreach ($products as $product) {
            $this->assertArrayHasKey('sku', $product);
            $this->assertArrayHasKey('title', $product);
            $this->assertArrayHasKey('quantity', $product);
            $this->assertArrayHasKey('price', $product);
        }
    }

    public function test_can_list_products_from_ebay_sandbox(): void
    {
        $connector = new EbayConnector([]);

        $products = $connector->listProducts();

        $this->assertIsArray($products);
        $this->assertNotEmpty($products);

        foreach ($products as $product) {
            $this->assertArrayHasKey('sku', $product);
            $this->assertArrayHasKey('title', $product);
            $this->assertArrayHasKey('quantity', $product);
            $this->assertArrayHasKey('price', $product);
        }
    }

    public function test_sync_channel_job_dispatched(): void
    {
        Queue::fake();

        SyncChannelJob::dispatch(1, 'amazon');

        Queue::assertPushed(SyncChannelJob::class, function (SyncChannelJob $job) {
            return $job->channelId === 1 && $job->type === 'amazon';
        });
    }

    public function test_amazon_sandbox_sync_inventory_returns_true(): void
    {
        $connector = new AmazonSpApiConnector([]);

        $result = $connector->syncInventory('MOCK-SKU-001', 10);

        $this->assertTrue($result);
    }

    public function test_ebay_sandbox_acknowledge_order_returns_true(): void
    {
        $connector = new EbayConnector([]);

        $result = $connector->acknowledgeOrder('EBAY-ORDER-001');

        $this->assertTrue($result);
    }

    public function test_channel_sync_service_makes_amazon_connector(): void
    {
        $service   = new ChannelSyncService();
        $connector = $service->makeConnector('amazon', []);

        $this->assertInstanceOf(AmazonSpApiConnector::class, $connector);
    }

    public function test_channel_sync_service_makes_ebay_connector(): void
    {
        $service   = new ChannelSyncService();
        $connector = $service->makeConnector('ebay', []);

        $this->assertInstanceOf(EbayConnector::class, $connector);
    }
}
