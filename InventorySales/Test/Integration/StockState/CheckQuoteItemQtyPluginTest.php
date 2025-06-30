<?php
/**
 * Copyright 2019 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventorySales\Test\Integration\StockState;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\CatalogInventory\Api\StockStateInterface;
use Magento\InventorySales\Plugin\StockState\CheckQuoteItemQtyPlugin;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Interception\PluginList;
use PHPUnit\Framework\TestCase;

/**
 * Verify CheckQuoteItemQty plugin functionality.
 */
class CheckQuoteItemQtyPluginTest extends TestCase
{
    /**
     * @var StockStateInterface
     */
    private $stockState;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->stockState = Bootstrap::getObjectManager()->get(StockStateInterface::class);
    }

    /**
     * Verify, CheckQuoteItemQtyPlugin is registered.
     *
     * @return void
     */
    public function testCheckQuoteItemQtyPluginIsRegistered(): void
    {
        $pluginInfo = Bootstrap::getObjectManager()->get(PluginList::class)->get(StockStateInterface::class, []);
        $this->assertSame(CheckQuoteItemQtyPlugin::class, $pluginInfo['check_quote_item_qty']['instance']);
    }

    /**
     * Verify, CheckQuoteItemQtyPlugin will handle exception in case requested product not assigned to stock.
     *
     * @magentoDataFixture Magento_InventorySalesApi::Test/_files/websites_with_stores.php
     * @magentoDataFixture Magento_InventoryApi::Test/_files/products.php
     * @magentoDataFixture Magento_InventoryApi::Test/_files/assign_products_to_websites.php
     * @magentoDataFixture Magento_InventoryApi::Test/_files/sources.php
     * @magentoDataFixture Magento_InventoryApi::Test/_files/stocks.php
     * @magentoDataFixture Magento_InventoryApi::Test/_files/stock_source_links.php
     * @magentoDataFixture Magento_InventorySalesApi::Test/_files/stock_website_sales_channels.php
     * @magentoDataFixture Magento_InventoryIndexer::Test/_files/reindex_inventory.php
     * @magentoDbIsolation disabled
     *
     * @return void
     */
    public function testAroundCheckQuoteItemQtyWithProductNotAssignedToStock(): void
    {
        $itemQty = $qtyToCheck = $origQty = 1;
        $storeManager = Bootstrap::getObjectManager()->get(StoreManagerInterface::class);
        $storeManager->setCurrentStore('store_for_eu_website');
        $websiteId = $storeManager->getWebsite()->getId();
        $product = Bootstrap::getObjectManager()->get(ProductRepositoryInterface::class)->get('SKU-1');
        $result = $this->stockState->checkQuoteItemQty($product->getId(), $itemQty, $qtyToCheck, $origQty, $websiteId);
        self::assertTrue($result->getHasError());
        self::assertEquals('The requested sku is not assigned to given stock.', $result->getMessage());
        self::assertEquals('The requested sku is not assigned to given stock.', $result->getQuoteMessage());
        self::assertEquals('qty', $result->getQuoteMessageIndex());
    }

    /**
     * Verify, CheckQuoteItemQtyPlugin does not indicated backordered for an in stock item with backorders on.
     *
     * @magentoDataFixture Magento_InventoryApi::Test/_files/products.php
     * @magentoDataFixture Magento_InventoryCatalog::Test/_files/source_items_on_default_source.php
     * @magentoConfigFixture current_store cataloginventory/item_options/backorders 2
     *
     * @return void
     */
    public function testCheckQuoteItemQtyPluginBackorderQtyWithInStockItem(): void
    {
        $itemQty = $qtyToCheck = $origQty = 1;
        $storeManager = Bootstrap::getObjectManager()->get(StoreManagerInterface::class);
        $websiteId = $storeManager->getDefaultStoreView()->getWebsiteId();
        $product = Bootstrap::getObjectManager()->get(ProductRepositoryInterface::class)->get('SKU-1');
        $result = $this->stockState->checkQuoteItemQty($product->getId(), $itemQty, $qtyToCheck, $origQty, $websiteId);
        self::assertFalse($result->getHasError());
        self::assertNull($result->getItemBackorders());
    }

    /**
     * Verify, CheckQuoteItemQtyPlugin indicates backordered for an out of stock item with backorders on.
     *
     * @magentoDataFixture Magento_InventoryApi::Test/_files/products.php
     * @magentoDataFixture Magento_InventoryCatalog::Test/_files/source_items_on_default_source.php
     * @magentoConfigFixture current_store cataloginventory/item_options/backorders 2
     *
     * @return void
     */
    public function testCheckQuoteItemQtyPluginBackorderQtyWithOutOfStockItem(): void
    {
        $itemQty = $qtyToCheck = $origQty = 10;
        $storeManager = Bootstrap::getObjectManager()->get(StoreManagerInterface::class);
        $websiteId = $storeManager->getDefaultStoreView()->getWebsiteId();
        $product = Bootstrap::getObjectManager()->get(ProductRepositoryInterface::class)->get('SKU-1');
        $result = $this->stockState->checkQuoteItemQty($product->getId(), $itemQty, $qtyToCheck, $origQty, $websiteId);
        self::assertFalse($result->getHasError());
        self::assertEquals($result->getItemBackorders(), 4.5);
    }
}
