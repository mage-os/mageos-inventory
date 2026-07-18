<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Test\Unit\Model;

use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Magento\InventoryApi\Api\Data\SourceInterface;
use Magento\InventoryApi\Api\GetSourcesAssignedToStockOrderedByPriorityInterface;
use Magento\InventoryReservationsApi\Model\SourceReservationsConfig;
use Magento\InventorySales\Model\ResourceModel\SourceReservation\GetReservationsQuantityBySkusAndSources;
use Magento\InventorySales\Model\ResourceModel\SourceReservation\GetSourceItemQuantityBySkusAndSources;
use Magento\InventorySalesApi\Api\GetProductSalableQtyInterface;
use Magento\InventoryStockVisualizer\Api\Data\SourceViewInterfaceFactory;
use Magento\InventoryStockVisualizer\Api\Data\StockViewInterfaceFactory;
use Magento\InventoryStockVisualizer\Model\Config;
use Magento\InventoryStockVisualizer\Model\Data\SourceView;
use Magento\InventoryStockVisualizer\Model\Data\StockView;
use Magento\InventoryStockVisualizer\Model\GetStockView;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @see GetStockView
 */
class GetStockViewTest extends TestCase
{
    private const SKU = 'SLR-1';
    private const STOCK_ID = 10;

    /**
     * @var GetProductSalableQtyInterface|MockObject
     */
    private $getProductSalableQty;

    /**
     * @var GetSourcesAssignedToStockOrderedByPriorityInterface|MockObject
     */
    private $getSourcesAssignedToStock;

    /**
     * @var GetSourceItemQuantityBySkusAndSources|MockObject
     */
    private $getSourceItemQuantity;

    /**
     * @var GetReservationsQuantityBySkusAndSources|MockObject
     */
    private $getSourceReservations;

    /**
     * @var SourceReservationsConfig|MockObject
     */
    private $sourceReservationsConfig;

    /**
     * @var Config|MockObject
     */
    private $config;

    /**
     * @var GetStockView
     */
    private $model;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->getProductSalableQty = $this->createMock(GetProductSalableQtyInterface::class);
        $this->getSourcesAssignedToStock = $this->createMock(
            GetSourcesAssignedToStockOrderedByPriorityInterface::class
        );
        $this->getSourceItemQuantity = $this->createMock(GetSourceItemQuantityBySkusAndSources::class);
        $this->getSourceReservations = $this->createMock(GetReservationsQuantityBySkusAndSources::class);
        $this->sourceReservationsConfig = $this->createMock(SourceReservationsConfig::class);
        $this->config = $this->createMock(Config::class);

        $stockViewFactory = $this->createMock(StockViewInterfaceFactory::class);
        $stockViewFactory->method('create')->willReturnCallback(
            static fn (array $args): StockView => new StockView(
                $args['sku'],
                $args['stockId'],
                $args['salableQty'],
                $args['sourceReservationsEnabled'],
                $args['sources']
            )
        );
        $sourceViewFactory = $this->createMock(SourceViewInterfaceFactory::class);
        $sourceViewFactory->method('create')->willReturnCallback(
            static fn (array $args): SourceView => new SourceView($args['sourceCode'], $args['qty'], $args['name'])
        );

        $this->model = new GetStockView(
            $this->getProductSalableQty,
            $this->getSourcesAssignedToStock,
            $this->getSourceItemQuantity,
            $this->getSourceReservations,
            $this->sourceReservationsConfig,
            $this->config,
            $stockViewFactory,
            $sourceViewFactory,
            $this->createMock(EventManagerInterface::class)
        );
    }

    /**
     * Aggregate scope returns only the salable quantity.
     *
     * @return void
     */
    public function testAggregateReturnsSalableQty(): void
    {
        $this->config->method('getScope')->willReturn(Config::SCOPE_AGGREGATE);
        $this->sourceReservationsConfig->method('isEnabled')->willReturn(true);
        $this->getProductSalableQty->method('execute')->willReturn(15.0);

        $view = $this->model->execute(self::SKU, self::STOCK_ID);

        $this->assertSame(15.0, $view->getSalableQty());
        $this->assertSame([], $view->getSources());
    }

    /**
     * Per-source availability nets source reservations when SLR is enabled.
     *
     * @return void
     */
    public function testPerSourceNetOfReservations(): void
    {
        $this->config->method('getScope')->willReturn(Config::SCOPE_PER_SOURCE);
        $this->sourceReservationsConfig->method('isEnabled')->willReturn(true);
        $this->getProductSalableQty->method('execute')->willReturn(12.0);
        $this->getSourcesAssignedToStock->method('execute')->willReturn([
            $this->source('slr_a', 'Source A', true),
            $this->source('slr_b', null, true),
            $this->source('slr_c', 'Disabled', false),
        ]);
        $this->getSourceItemQuantity->method('execute')->willReturn([
            'slr_a' => [self::SKU => 10.0],
            'slr_b' => [self::SKU => 4.0],
        ]);
        $this->getSourceReservations->method('execute')->willReturn([
            'slr_a' => [self::SKU => -3.0],
        ]);

        $sources = $this->model->execute(self::SKU, self::STOCK_ID)->getSources();

        $this->assertCount(2, $sources);
        $this->assertSame('slr_a', $sources[0]->getSourceCode());
        $this->assertSame(7.0, $sources[0]->getQty());
        $this->assertSame('Source A', $sources[0]->getName());
        $this->assertSame(4.0, $sources[1]->getQty());
        $this->assertSame('slr_b', $sources[1]->getName());
    }

    /**
     * Per-source availability ignores reservations when SLR is disabled.
     *
     * @return void
     */
    public function testPerSourceIgnoresReservationsWhenSlrDisabled(): void
    {
        $this->config->method('getScope')->willReturn(Config::SCOPE_PER_SOURCE);
        $this->sourceReservationsConfig->method('isEnabled')->willReturn(false);
        $this->getProductSalableQty->method('execute')->willReturn(6.0);
        $this->getSourcesAssignedToStock->method('execute')->willReturn([
            $this->source('slr_a', 'Source A', true),
        ]);
        $this->getSourceItemQuantity->method('execute')->willReturn(['slr_a' => [self::SKU => 6.0]]);
        $this->getSourceReservations->expects($this->never())->method('execute');

        $sources = $this->model->execute(self::SKU, self::STOCK_ID)->getSources();

        $this->assertCount(1, $sources);
        $this->assertSame(6.0, $sources[0]->getQty());
    }

    /**
     * @param string $code
     * @param string|null $name
     * @param bool $enabled
     * @return SourceInterface|MockObject
     */
    private function source(string $code, ?string $name, bool $enabled)
    {
        $source = $this->createMock(SourceInterface::class);
        $source->method('getSourceCode')->willReturn($code);
        $source->method('getName')->willReturn($name);
        $source->method('isEnabled')->willReturn($enabled);

        return $source;
    }
}
