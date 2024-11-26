<?php
/**
 * Copyright 2024 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Api;

use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Cron\Console\Command\CronCommand;
use Magento\InventoryApi\Test\Fixture\Source as SourceFixture;
use Magento\InventoryApi\Test\Fixture\SourceItems as SourceItemsFixture;
use Magento\InventoryApi\Test\Fixture\Stock as StockFixture;
use Magento\InventoryApi\Test\Fixture\StockSourceLinks as StockSourceLinksFixture;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Webapi\Rest\Request;
use Magento\InventorySalesApi\Api\IsProductSalableInterface;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorage;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\WebapiAbstract;
use PHPUnit\Framework\MockObject\MockObject as Mock;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\InventorySalesApi\Test\Fixture\StockSalesChannels as StockSalesChannelsFixture;

class StockItemRepositoryTest extends WebapiAbstract
{
    const SERVICE_NAME = 'catalogProductRepositoryV1';
    const SERVICE_VERSION = 'V1';
    const RESOURCE_PATH = '/V1/products/';

    /**
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @var DataFixtureStorage
     */
    private $fixtures;

    /**
     * @var CronCommand
     */
    private $command;

    /**
     * @var InputInterface|Mock
     */
    private $inputMock;

    /**
     * @var OutputInterface|Mock
     */
    private $outputMock;

    /**
     * @var IsProductSalableInterface
     */
    private $isProductSalable;

    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->fixtures = DataFixtureStorageManager::getStorage();
        $this->command = $this->objectManager->get(CronCommand::class);
        $this->isProductSalable = $this->objectManager->get(IsProductSalableInterface::class);
        $this->inputMock = $this->getMockBuilder(InputInterface::class)->getMockForAbstractClass();
        $this->outputMock = $this->getMockBuilder(OutputInterface::class)->getMockForAbstractClass();
    }


    /**
     * Test stock status for product after stock item update
     */
    #[
        Config('cataloginventory/options/show_out_of_stock', '1'),
        DataFixture(SourceFixture::class, as: 'src1'),
        DataFixture(StockFixture::class, as: 'stk2'),
        DataFixture(
            StockSourceLinksFixture::class,
            [
                ['stock_id' => '$stk2.stock_id$', 'source_code' => '$src1.source_code$'],
            ]
        ),
        DataFixture(
            StockSalesChannelsFixture::class,
            ['stock_id' => '$stk2.stock_id$', 'sales_channels' => ['base']]
        ),
        DataFixture(ProductFixture::class, as: 'p1'),
        DataFixture(
            SourceItemsFixture::class,
            [
                ['sku' => '$p1.sku$', 'source_code' => 'default', 'quantity' => 0],
                ['sku' => '$p1.sku$', 'source_code' => '$src1.source_code$', 'quantity' => 0],
            ]
        ),
    ]
    public function testProductIsInStockAfterUpdateStockItem(): void
    {
        $product = $this->fixtures->get('p1');
        $stockId = $this->fixtures->get('stk2')->getStockId();
        $productSku = $product->getSku();
        $isSalable = $this->isProductSalable->execute($productSku, $stockId);
        $this->assertFalse($isSalable);
        $productParams =  [
                "sku"=> $productSku,
                "extension_attributes"=> [
                    "stock_item"=> [
                        "stock_id"=> $stockId,
                        "is_in_stock"=> 1,
                        "use_config_backorders"=> false,
                        "backorders"=> 1,
                    ]
                ],
        ];
        $serviceInfo = [
            'rest' => [
                'resourcePath' => self::RESOURCE_PATH,
                'httpMethod' => Request::HTTP_METHOD_POST,
            ],
            'soap' => [
                'service' => self::SERVICE_NAME,
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => self::SERVICE_NAME . 'Save',
            ],
        ];
        $this->_webApiCall($serviceInfo, ['product' => $productParams]);
        $isSalableAfterUpdate = $this->isProductSalable->execute($productSku, $stockId);
        $this->assertTrue($isSalableAfterUpdate);
    }
}
