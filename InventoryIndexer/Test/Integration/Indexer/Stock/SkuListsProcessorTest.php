<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryIndexer\Test\Integration\Indexer\Stock;

use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\ResourceConnection;
use Magento\InventoryApi\Api\SourceItemRepositoryInterface;
use Magento\InventoryApi\Test\Fixture\Source as SourceFixture;
use Magento\InventoryApi\Test\Fixture\SourceItems as SourceItemsFixture;
use Magento\InventoryApi\Test\Fixture\Stock as StockFixture;
use Magento\InventoryApi\Test\Fixture\StockSourceLinks as StockSourceLinksFixture;
use Magento\InventoryIndexer\Indexer\SourceItem\GetSourceItemIds;
use Magento\InventoryIndexer\Indexer\SourceItem\SourceItemIndexer;
use Magento\InventoryIndexer\Test\Integration\Indexer\RemoveIndexData as RemoveIndexData;
use Magento\InventoryIndexer\Model\ResourceModel\GetStockItemData;
use Magento\InventorySalesApi\Test\Fixture\StockSalesChannels as StockSalesChannelsFixture;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use function PHPUnit\Framework\assertCount;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class SkuListsProcessorTest extends TestCase
{
    /**
     * @var SourceItemIndexer
     */
    private $sourceItemIndexer;

    /**
     * @var GetStockItemData
     */
    private $getStockItemData;

    /**
     * @var GetSourceItemIds
     */
    private $getSourceItemIds;

    /**
     * @var RemoveIndexData
     */
    private $removeIndexData;

    /**
     * @var SourceItemRepositoryInterface
     */
    private $sourceItemRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var ResourceConnection
     */
    private $resource;

    protected function setUp(): void
    {
        $this->sourceItemIndexer = Bootstrap::getObjectManager()->get(SourceItemIndexer::class);
        $this->getStockItemData = Bootstrap::getObjectManager()->get(GetStockItemData::class);
        $this->getSourceItemIds = Bootstrap::getObjectManager()->get(GetSourceItemIds::class);
        $this->sourceItemRepository = Bootstrap::getObjectManager()->get(SourceItemRepositoryInterface::class);
        $this->searchCriteriaBuilder = Bootstrap::getObjectManager()->get(SearchCriteriaBuilder::class);
        $this->removeIndexData = Bootstrap::getObjectManager()->get(RemoveIndexData::class);
        $this->resource = Bootstrap::getObjectManager()->get(ResourceConnection::class);
    }

    /**
     * We broke transaction during indexation so we need to clean db state manually
     */
    protected function tearDown(): void
    {
        $this->removeIndexData->execute([2]);
    }

    /**
     * Product should stay in inventory stock if related product is updated and reindex is run.
     *
     */
    #[
        DbIsolation(false),
        AppIsolation(true),
        DataFixture(SourceFixture::class, as: 'source2'),
        DataFixture(StockFixture::class, as: 'stock2'),
        DataFixture(
            StockSourceLinksFixture::class,
            [['stock_id' => '$stock2.stock_id$', 'source_code' => '$source2.source_code$'],]
        ),
        DataFixture(
            StockSalesChannelsFixture::class,
            ['stock_id' => '$stock2.stock_id$', 'sales_channels' => ['base']]
        ),
        DataFixture(ProductFixture::class, as: 'p1'),
        DataFixture(ProductFixture::class, ['product_links' => [['sku' => '$p1.sku$', 'type' => 'related']]], 'p2'),
        DataFixture(ProductFixture::class, ['product_links' => [['sku' => '$p1.sku$', 'type' => 'upsell']]], 'p3'),
        DataFixture(ProductFixture::class, ['product_links' => [['sku' => '$p1.sku$', 'type' => 'crosssell']]], 'p4'),
        DataFixture(
            SourceItemsFixture::class,
            [
                ['sku' => '$p1.sku$', 'source_code' => '$source2.source_code$', 'quantity' => 100],
                ['sku' => '$p2.sku$', 'source_code' => '$source2.source_code$', 'quantity' => 200],
                ['sku' => '$p3.sku$', 'source_code' => '$source2.source_code$', 'quantity' => 300],
                ['sku' => '$p4.sku$', 'source_code' => '$source2.source_code$', 'quantity' => 400],
            ]
        ),
    ]
    public function testReindexListForRelatedProducts()
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName('inventory_stock_2'))
            ->order('sku ASC');
        $inventoryData = $connection->fetchAll($select);

        // All products should be in inventory_stock_2 table, none should be deleted at reindex.
        // Reindex is triggered when source is assigned to products
        assertCount(4, $inventoryData, 'All 4 products should be present in inventory_stock_2 table');
    }
}

