<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryAdminUi\Test\Unit\Controller\Adminhtml\Source;

use Magento\InventoryAdminUi\Controller\Adminhtml\Source\MassDelete;
use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Message\ManagerInterface;
use Magento\Inventory\Model\ResourceModel\Source as SourceResource;
use Magento\Inventory\Model\ResourceModel\Source\Collection;
use Magento\InventoryCatalogApi\Api\DefaultSourceProviderInterface;
use Magento\InventoryApi\Api\GetStockSourceLinksInterface;
use Magento\InventoryApi\Api\SourceItemRepositoryInterface;
use Magento\InventoryApi\Api\Data\SourceItemSearchResultsInterface;
use Magento\InventoryApi\Api\Data\StockSourceLinkSearchResultsInterface;
use Magento\Inventory\Model\Source as SourceModel;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class MassDeleteTest extends TestCase
{
    /** @var DefaultSourceProviderInterface|MockObject */
    private $defaultSourceProvider;

    /** @var GetStockSourceLinksInterface|MockObject */
    private $getStockSourceLinks;

    /** @var SourceItemRepositoryInterface|MockObject */
    private $sourceItemRepository;

    /** @var SearchCriteriaBuilder|MockObject */
    private $searchCriteriaBuilder;

    /** @var SourceResource|MockObject */
    private $sourceResource;

    /** @var ManagerInterface|MockObject */
    private $messageManager;

    /** @var MassDelete */
    private $controller;

    protected function setUp(): void
    {
        // We disable constructor and inject only what deleteSources() needs.
        $this->controller = $this->getMockBuilder(MassDelete::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $this->defaultSourceProvider = $this->getMockBuilder(DefaultSourceProviderInterface::class)->getMock();
        $this->getStockSourceLinks   = $this->getMockBuilder(GetStockSourceLinksInterface::class)->getMock();
        $this->sourceItemRepository  = $this->getMockBuilder(SourceItemRepositoryInterface::class)->getMock();
        $this->searchCriteriaBuilder = $this->getMockBuilder(SearchCriteriaBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['addFilter', 'create', 'setFilterGroups'])
            ->getMock();
        $this->sourceResource        = $this->getMockBuilder(SourceResource::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->messageManager        = $this->getMockBuilder(ManagerInterface::class)->getMock();

        // Reflection: set required properties on controller (inherited/protected)
        $this->setProperty($this->controller, 'defaultSourceProvider', $this->defaultSourceProvider);
        $this->setProperty($this->controller, 'getStockSourceLinks', $this->getStockSourceLinks);
        $this->setProperty($this->controller, 'sourceItemRepository', $this->sourceItemRepository);
        $this->setProperty($this->controller, 'searchCriteriaBuilder', $this->searchCriteriaBuilder);
        $this->setProperty($this->controller, 'sourceResource', $this->sourceResource);
        $this->setProperty($this->controller, 'messageManager', $this->messageManager);
    }

    public function testDeleteSourcesSkipsDefaultAndLinkedAndDeletesClean(): void
    {
        // Arrange sources: default, linked, clean
        $srcDefault = $this->getMockBuilder(SourceModel::class)->disableOriginalConstructor()->getMock();
        $srcLinked  = $this->getMockBuilder(SourceModel::class)->disableOriginalConstructor()->getMock();
        $srcClean   = $this->getMockBuilder(SourceModel::class)->disableOriginalConstructor()->getMock();

        $srcDefault->method('getSourceCode')->willReturn('default');
        $srcLinked->method('getSourceCode')->willReturn('linked');
        $srcClean->method('getSourceCode')->willReturn('clean');

        $collection = $this->getMockBuilder(Collection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getIterator'])
            ->getMock();
        $collection->method('getIterator')->willReturn(new \ArrayIterator([$srcDefault, $srcLinked, $srcClean]));

        // Default source code
        $this->defaultSourceProvider->method('getCode')->willReturn('default');

        // SearchCriteriaBuilder reusable stubs
        $dummyCriteria = $this->createMock(SearchCriteria::class);
        $this->searchCriteriaBuilder->method('addFilter')->willReturn($this->searchCriteriaBuilder);
        $this->searchCriteriaBuilder->method('create')->willReturn($dummyCriteria);
        $this->searchCriteriaBuilder->method('setFilterGroups')->with([])->willReturnSelf();

        // Links: for 'linked' -> count=1, for 'clean' -> count=0
        $linkResults1 = $this->getMockBuilder(StockSourceLinkSearchResultsInterface::class)->getMock();
        $linkResults1->method('getTotalCount')->willReturn(1);
        $linkResults0 = $this->getMockBuilder(StockSourceLinkSearchResultsInterface::class)->getMock();
        $linkResults0->method('getTotalCount')->willReturn(0);
        // The controller calls execute twice: once for 'linked', once for 'clean' (default is skipped before)
        $this->getStockSourceLinks->expects($this->exactly(2))
            ->method('execute')
            ->with($dummyCriteria)
            ->willReturnOnConsecutiveCalls($linkResults1, $linkResults0);

        // Items: only checked for 'clean' (since linked is skipped), return 0
        $itemResults0 = $this->getMockBuilder(SourceItemSearchResultsInterface::class)->getMock();
        $itemResults0->method('getTotalCount')->willReturn(0);
        $this->sourceItemRepository->expects($this->once())
            ->method('getList')
            ->with($dummyCriteria)
            ->willReturn($itemResults0);

        // Delete called exactly once for 'clean'
        $this->sourceResource->expects($this->once())
            ->method('delete')
            ->with($srcClean);

        // Messages: one success with count=1, two notices (default + linked)
        $this->messageManager->expects($this->once())
            ->method('addSuccessMessage')
            ->with($this->callback(function ($phrase) {
                return (string)$phrase === 'A total of 1 record(s) have been deleted.';
            }));
        $this->messageManager->expects($this->exactly(2))
            ->method('addNoticeMessage');

        // Act: call private deleteSources via reflection
        $this->invokeDeleteSources($collection);
    }

    // Helpers

    private function setProperty(object $obj, string $prop, $value): void
    {
        $ref = new \ReflectionClass($obj);
        $property = null;
        while ($ref && !$property) {
            if ($ref->hasProperty($prop)) {
                $property = $ref->getProperty($prop);
                break;
            }
            $ref = $ref->getParentClass();
        }
        if (!$property) {
            $this->fail('Property not found: ' . $prop);
        }
        $property->setValue($obj, $value);
    }

    private function invokeDeleteSources(Collection $collection): void
    {
        $ref = new \ReflectionClass($this->controller);
        $method = $ref->getMethod('deleteSources');
        $method->invoke($this->controller, $collection);
    }
}
