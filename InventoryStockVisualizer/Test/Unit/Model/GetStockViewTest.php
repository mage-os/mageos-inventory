<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Test\Unit\Model;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Magento\InventoryApi\Api\Data\SourceInterface;
use Magento\InventoryApi\Api\GetSourcesAssignedToStockOrderedByPriorityInterface;
use Magento\InventoryConfigurationApi\Model\IsSourceItemManagementAllowedForProductTypeInterface;
use Magento\InventoryReservationsApi\Model\SourceReservationsConfig;
use Magento\InventorySales\Model\ResourceModel\SourceReservation\GetReservationsQuantityBySkusAndSources;
use Magento\InventorySales\Model\ResourceModel\SourceReservation\GetSourceItemQuantityBySkusAndSources;
use Magento\InventorySalesApi\Api\GetProductSalableQtyInterface;
use Magento\InventorySalesApi\Model\GetStockItemDataInterface;
use Magento\InventoryStockVisualizer\Api\Data\ChildViewInterfaceFactory;
use Magento\InventoryStockVisualizer\Api\Data\SourceViewInterfaceFactory;
use Magento\InventoryStockVisualizer\Api\Data\StockViewInterfaceFactory;
use Magento\InventoryStockVisualizer\Model\Availability\GetCompositeChildren;
use Magento\InventoryStockVisualizer\Model\Config;
use Magento\InventoryStockVisualizer\Model\Data\ChildView;
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
     * @var IsSourceItemManagementAllowedForProductTypeInterface|MockObject
     */
    private $isSourceItemManagementAllowed;

    /**
     * @var GetStockItemDataInterface|MockObject
     */
    private $getStockItemData;

    /**
     * @var ProductRepositoryInterface|MockObject
     */
    private $productRepository;

    /**
     * @var GetCompositeChildren|MockObject
     */
    private $getCompositeChildren;

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
        $this->isSourceItemManagementAllowed = $this->createMock(
            IsSourceItemManagementAllowedForProductTypeInterface::class
        );
        $this->getStockItemData = $this->createMock(GetStockItemDataInterface::class);
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->getCompositeChildren = $this->createMock(GetCompositeChildren::class);

        // Composite types are not source-item managed; everything else behaves as stockable.
        $composite = ['configurable', 'grouped', 'bundle'];
        $this->isSourceItemManagementAllowed->method('execute')->willReturnCallback(
            static fn (string $type): bool => !in_array($type, $composite, true)
        );
        // When no type id is passed, resolution loads the product; default to a stockable type.
        $product = $this->createMock(\Magento\Catalog\Api\Data\ProductInterface::class);
        $product->method('getTypeId')->willReturn('simple');
        $this->productRepository->method('get')->willReturn($product);

        $stockViewFactory = $this->createMock(StockViewInterfaceFactory::class);
        $stockViewFactory->method('create')->willReturnCallback(
            static fn (array $args): StockView => new StockView(
                $args['sku'],
                $args['stockId'],
                $args['salableQty'],
                $args['sourceReservationsEnabled'],
                $args['sources'] ?? [],
                $args['salable'] ?? null,
                $args['aggregateOnly'] ?? false,
                $args['children'] ?? []
            )
        );
        $sourceViewFactory = $this->createMock(SourceViewInterfaceFactory::class);
        $sourceViewFactory->method('create')->willReturnCallback(
            static fn (array $args): SourceView => new SourceView($args['sourceCode'], $args['qty'], $args['name'])
        );
        $childViewFactory = $this->createMock(ChildViewInterfaceFactory::class);
        $childViewFactory->method('create')->willReturnCallback(
            static fn (array $args): ChildView => new ChildView(
                $args['sku'],
                $args['label'],
                $args['qty'],
                $args['salable']
            )
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
            $this->createMock(EventManagerInterface::class),
            $this->isSourceItemManagementAllowed,
            $this->getStockItemData,
            $this->productRepository,
            $this->getCompositeChildren,
            $childViewFactory
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
     * A composite type yields an aggregate-only salable view read from the index, never the qty API.
     *
     * @return void
     */
    public function testCompositeSalableFromIndex(): void
    {
        $this->sourceReservationsConfig->method('isEnabled')->willReturn(true);
        $this->getProductSalableQty->expects($this->never())->method('execute');
        $this->getStockItemData->expects($this->once())
            ->method('execute')
            ->with(self::SKU, self::STOCK_ID)
            ->willReturn([GetStockItemDataInterface::QUANTITY => 7.0, GetStockItemDataInterface::IS_SALABLE => 1]);

        $view = $this->model->execute(self::SKU, self::STOCK_ID, 'configurable');

        $this->assertTrue($view->isAggregateOnly());
        $this->assertTrue($view->isSalable());
        $this->assertSame([], $view->getSources());
        $this->assertSame(0.0, $view->getSalableQty());
    }

    /**
     * A composite type not salable in the index is reported out of stock.
     *
     * @return void
     */
    public function testCompositeOutOfStockFromIndex(): void
    {
        $this->getStockItemData->method('execute')
            ->willReturn([GetStockItemDataInterface::QUANTITY => 0.0, GetStockItemDataInterface::IS_SALABLE => 0]);

        $view = $this->model->execute(self::SKU, self::STOCK_ID, 'bundle');

        $this->assertTrue($view->isAggregateOnly());
        $this->assertFalse($view->isSalable());
    }

    /**
     * A composite with no index row (null) is out of stock without throwing.
     *
     * @return void
     */
    public function testCompositeMissingIndexRowIsOutOfStock(): void
    {
        $this->getStockItemData->method('execute')->willReturn(null);

        $view = $this->model->execute(self::SKU, self::STOCK_ID, 'grouped');

        $this->assertTrue($view->isAggregateOnly());
        $this->assertFalse($view->isSalable());
    }

    /**
     * Children mode lists each child's salable quantity and reflects overall salability.
     *
     * @return void
     */
    public function testChildrenModeListsChildren(): void
    {
        $this->config->method('getConfigurableMode')->willReturn(Config::COMPOSITE_MODE_CHILDREN);
        $this->getStockItemData->expects($this->never())->method('execute');
        $this->getCompositeChildren->method('execute')->willReturn([
            ['sku' => 'VAR-1', 'label' => 'Variant 1'],
            ['sku' => 'VAR-2', 'label' => 'Variant 2'],
        ]);
        $this->getProductSalableQty->method('execute')->willReturnMap([
            ['VAR-1', self::STOCK_ID, 5.0],
            ['VAR-2', self::STOCK_ID, 0.0],
        ]);

        $view = $this->model->execute(self::SKU, self::STOCK_ID, 'configurable');

        $this->assertTrue($view->isAggregateOnly());
        $this->assertTrue($view->isSalable());
        $children = $view->getChildren();
        $this->assertCount(2, $children);
        $this->assertSame('VAR-1', $children[0]->getSku());
        $this->assertSame('Variant 1', $children[0]->getLabel());
        $this->assertSame(5.0, $children[0]->getQty());
        $this->assertTrue($children[0]->isSalable());
        $this->assertSame(0.0, $children[1]->getQty());
        $this->assertFalse($children[1]->isSalable());
    }

    /**
     * Children mode falls back to the aggregate status when the parent has no children.
     *
     * @return void
     */
    public function testChildrenModeFallsBackToStatusWhenEmpty(): void
    {
        $this->config->method('getBundleMode')->willReturn(Config::COMPOSITE_MODE_CHILDREN);
        $this->getCompositeChildren->method('execute')->willReturn([]);
        $this->getStockItemData->method('execute')
            ->willReturn([GetStockItemDataInterface::IS_SALABLE => 1]);

        $view = $this->model->execute(self::SKU, self::STOCK_ID, 'bundle');

        $this->assertTrue($view->isAggregateOnly());
        $this->assertTrue($view->isSalable());
        $this->assertSame([], $view->getChildren());
    }

    /**
     * A stockable type keeps the quantity path and never reads the aggregate index.
     *
     * @return void
     */
    public function testStockableTypeUsesQtyPathNotIndex(): void
    {
        $this->config->method('getScope')->willReturn(Config::SCOPE_AGGREGATE);
        $this->sourceReservationsConfig->method('isEnabled')->willReturn(false);
        $this->getStockItemData->expects($this->never())->method('execute');
        $this->getProductSalableQty->method('execute')->willReturn(9.0);

        $view = $this->model->execute(self::SKU, self::STOCK_ID, 'simple');

        $this->assertFalse($view->isAggregateOnly());
        $this->assertTrue($view->isSalable());
        $this->assertSame(9.0, $view->getSalableQty());
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
