<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Test\Integration;

use Magento\InventoryCatalog\Model\GetStockIdForCurrentWebsite;
use Magento\InventoryStockVisualizer\Api\GetStockViewInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The availability view must resolve against the stock assigned to the CURRENT website's
 * sales channel, derived server-side. This guards the multi-website case: the same SKU
 * resolves to a different salable quantity per website, so one website's cached fragment
 * can never be a correct substitute for another's.
 *
 * @magentoDbIsolation disabled
 */
class StockResolutionPerWebsiteTest extends TestCase
{
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var GetStockIdForCurrentWebsite
     */
    private $getStockIdForCurrentWebsite;

    /**
     * @var GetStockViewInterface
     */
    private $getStockView;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $objectManager = Bootstrap::getObjectManager();
        $this->storeManager = $objectManager->get(StoreManagerInterface::class);
        $this->getStockIdForCurrentWebsite = $objectManager->get(GetStockIdForCurrentWebsite::class);
        $this->getStockView = $objectManager->get(GetStockViewInterface::class);
    }

    /**
     * The current website resolves to its own stock and the SKU's salable quantity on it.
     *
     * @param string $storeCode
     * @param int $expectedStockId
     * @param string $sku
     * @param float $expectedQty
     * @return void
     * @magentoDataFixture Magento_InventoryApi::Test/_files/products.php
     * @magentoDataFixture Magento_InventoryApi::Test/_files/sources.php
     * @magentoDataFixture Magento_InventoryApi::Test/_files/stocks.php
     * @magentoDataFixture Magento_InventoryApi::Test/_files/stock_source_links.php
     * @magentoDataFixture Magento_InventoryApi::Test/_files/source_items.php
     * @magentoDataFixture Magento_InventorySalesApi::Test/_files/websites_with_stores.php
     * @magentoDataFixture Magento_InventorySalesApi::Test/_files/stock_website_sales_channels.php
     * @magentoDataFixture Magento_InventoryIndexer::Test/_files/reindex_inventory.php
     */
    #[DataProvider('perWebsiteProvider')]
    public function testAvailabilityResolvesPerWebsite(
        string $storeCode,
        int $expectedStockId,
        string $sku,
        float $expectedQty
    ): void {
        $this->storeManager->setCurrentStore($storeCode);

        $stockId = $this->getStockIdForCurrentWebsite->execute();
        $this->assertSame($expectedStockId, $stockId, 'The current website must resolve to its own stock.');

        $view = $this->getStockView->execute($sku, $stockId);
        $this->assertEqualsWithDelta(
            $expectedQty,
            $view->getSalableQty(),
            0.0001,
            'The salable quantity must match the current website stock, not another website.'
        );
    }

    /**
     * Same SKU, opposite availability depending on which website (stock) is current.
     *
     * @return array<string, array{0: string, 1: int, 2: string, 3: float}>
     */
    public static function perWebsiteProvider(): array
    {
        return [
            'EU website sees SKU-1 in stock on the EU stock' => ['store_for_eu_website', 10, 'SKU-1', 8.5],
            'US website sees the same SKU-1 out of stock' => ['store_for_us_website', 20, 'SKU-1', 0.0],
            'US website sees SKU-2 in stock on the US stock' => ['store_for_us_website', 20, 'SKU-2', 5.0],
            'EU website sees the same SKU-2 out of stock' => ['store_for_eu_website', 10, 'SKU-2', 0.0],
        ];
    }
}
