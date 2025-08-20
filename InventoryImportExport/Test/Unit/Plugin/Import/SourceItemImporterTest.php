<?php
/**
 * Copyright 2022 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryImportExport\Test\Unit\Plugin\Import;

use Magento\CatalogImportExport\Model\StockItemProcessorInterface;
use Magento\Framework\EntityManager\EventManager;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Validation\ValidationException;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\Data\SourceItemInterfaceFactory;
use Magento\InventoryApi\Api\GetSourceItemsBySkuInterface;
use Magento\InventoryApi\Api\SourceItemsSaveInterface;
use Magento\InventoryImportExport\Model\Import\SourceResolver;
use Magento\InventoryImportExport\Plugin\Import\SourceItemImporter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests for SourceItemImporter class.
 */
class SourceItemImporterTest extends TestCase
{
    /**
     * @var SourceItemsSaveInterface|MockObject
     */
    private SourceItemsSaveInterface $sourceItemsSaveMock;

    /**
     * @var SourceItemInterfaceFactory|MockObject
     */
    private SourceItemInterfaceFactory $sourceItemFactoryMock;

    /**
     * @var GetSourceItemsBySkuInterface|MockObject
     */
    private GetSourceItemsBySkuInterface $sourceItemsBySku;

    /**
     * @var SourceResolver|MockObject
     */
    private SourceResolver $sourceResolver;

    /**
     * @var EventManager|MockObject
     */
    private EventManager $eventManager;

    /**
     * @var SourceItemImporter
     */
    private SourceItemImporter $plugin;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->sourceItemsSaveMock = $this->getMockBuilder(SourceItemsSaveInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->sourceItemFactoryMock = $this->createMock(SourceItemInterfaceFactory::class);
        $this->sourceItemsBySku = $this->createMock(GetSourceItemsBySkuInterface::class);
        $this->sourceResolver = $this->createMock(SourceResolver::class);
        $this->eventManager = $this->createMock(EventManager::class);

        $this->plugin = new SourceItemImporter(
            $this->sourceItemsSaveMock,
            $this->sourceItemFactoryMock,
            $this->sourceItemsBySku,
            $this->sourceResolver,
            $this->eventManager
        );
    }

    /**
     * @return void
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws ValidationException
     * @throws \PHPUnit\Framework\MockObject\Exception
     */
    public function testNoSourceItemsToSave(): void
    {
        $stockData = [];
        $this->sourceItemsSaveMock->expects($this->never())->method('execute');
        $this->plugin->afterProcess($this->createMock(StockItemProcessorInterface::class), '', $stockData, []);
    }

    /**
     * @return void
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws ValidationException
     * @throws \PHPUnit\Framework\MockObject\Exception
     */
    public function testSourceItemNoStockAdjustment(): void
    {
        $defaultStoreId = 1;
        $sku = 'test-sku';
        $defaultStockSourceQuantity = 7.00;
        $stockData[$sku][$defaultStoreId] = [];
        $importedData[$sku][$defaultStoreId] = [];

        $this->sourceResolver->expects($this->once())->method('getSourcesForStore')->willReturn(['default']);
        $sourceItem = $this->createMock(SourceItemInterface::class);
        $sourceItem->expects($this->once())
            ->method('getSourceCode')
            ->willReturn('default');
        $sourceItem->expects($this->once())->method('getQuantity')->willReturn($defaultStockSourceQuantity);
        $this->sourceItemsBySku->expects($this->once())->method('execute')->with($sku)->willReturn([$sourceItem]);

        $savedSourceItem = $this->createMock(SourceItemInterface::class);
        $savedSourceItem->expects($this->once())->method('setSku')->with($sku);
        $savedSourceItem->expects($this->once())->method('setSourceCode')->with('default');
        $savedSourceItem->expects($this->once())->method('setQuantity')->with($defaultStockSourceQuantity);
        $savedSourceItem->expects($this->once())
            ->method('setStatus')
            ->with(SourceItemInterface::STATUS_IN_STOCK);
        $this->sourceItemFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($savedSourceItem);

        $this->eventManager->expects($this->once())
            ->method('dispatch')
            ->with(
                'before_import_source_items_save',
                ['source_items' => [$savedSourceItem]]
            );
        $this->sourceItemsSaveMock->expects($this->once())->method('execute')->with([$savedSourceItem]);
        $this->plugin->afterProcess(
            $this->createMock(StockItemProcessorInterface::class),
            '',
            $stockData,
            $importedData
        );
    }

    /**
     * @return void
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws ValidationException
     * @throws \PHPUnit\Framework\MockObject\Exception
     */
    public function testSourceItemWithStockAdjustment(): void
    {
        $defaultStoreId = 1;
        $sku = 'test-sku';
        $defaultStockSourceQuantity = 7.00;
        $stockData[$sku][$defaultStoreId] = [
            'min_qty' => 8
        ];
        $importedData[$sku][$defaultStoreId] = [];

        $this->sourceResolver->expects($this->once())->method('getSourcesForStore')->willReturn(['default']);
        $sourceItem = $this->createMock(SourceItemInterface::class);
        $sourceItem->expects($this->once())
            ->method('getSourceCode')
            ->willReturn('default');
        $sourceItem->expects($this->once())->method('getQuantity')->willReturn($defaultStockSourceQuantity);
        $this->sourceItemsBySku->expects($this->once())->method('execute')->with($sku)->willReturn([$sourceItem]);

        $savedSourceItem = $this->createMock(SourceItemInterface::class);
        $savedSourceItem->expects($this->once())->method('setSku')->with($sku);
        $savedSourceItem->expects($this->once())->method('setSourceCode')->with('default');
        $savedSourceItem->expects($this->once())->method('setQuantity')->with($defaultStockSourceQuantity);
        $savedSourceItem->expects($this->once())
            ->method('setStatus')
            ->with(SourceItemInterface::STATUS_OUT_OF_STOCK);
        $this->sourceItemFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($savedSourceItem);

        $this->plugin->afterProcess(
            $this->createMock(StockItemProcessorInterface::class),
            '',
            $stockData,
            $importedData
        );
    }
}
