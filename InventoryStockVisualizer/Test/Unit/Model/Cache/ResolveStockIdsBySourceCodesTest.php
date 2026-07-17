<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Test\Unit\Model\Cache;

use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\InventoryApi\Api\Data\StockSourceLinkInterface;
use Magento\InventoryApi\Api\Data\StockSourceLinkSearchResultsInterface;
use Magento\InventoryApi\Api\GetStockSourceLinksInterface;
use Magento\InventoryStockVisualizer\Model\Cache\ResolveStockIdsBySourceCodes;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @see ResolveStockIdsBySourceCodes
 */
class ResolveStockIdsBySourceCodesTest extends TestCase
{
    /**
     * @var GetStockSourceLinksInterface|MockObject
     */
    private $getStockSourceLinks;

    /**
     * @var SearchCriteriaBuilder|MockObject
     */
    private $searchCriteriaBuilder;

    /**
     * @var ResolveStockIdsBySourceCodes
     */
    private $model;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->getStockSourceLinks = $this->createMock(GetStockSourceLinksInterface::class);
        $this->searchCriteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);
        $this->model = new ResolveStockIdsBySourceCodes($this->getStockSourceLinks, $this->searchCriteriaBuilder);
    }

    /**
     * No source codes short-circuit without querying.
     *
     * @return void
     */
    public function testEmptyReturnsEmpty(): void
    {
        $this->getStockSourceLinks->expects($this->never())->method('execute');

        $this->assertSame([], $this->model->execute([]));
    }

    /**
     * Links are grouped by source code into the list of stock ids.
     *
     * @return void
     */
    public function testGroupsStockIdsBySourceCode(): void
    {
        $this->searchCriteriaBuilder->method('addFilter')
            ->with(StockSourceLinkInterface::SOURCE_CODE, ['slr_a'], 'in')
            ->willReturnSelf();
        $this->searchCriteriaBuilder->method('create')->willReturn($this->createMock(SearchCriteria::class));

        $results = $this->createMock(StockSourceLinkSearchResultsInterface::class);
        $results->method('getItems')->willReturn([
            $this->link('slr_a', 10),
            $this->link('slr_a', 30),
        ]);
        $this->getStockSourceLinks->method('execute')->willReturn($results);

        $this->assertSame(['slr_a' => [10, 30]], $this->model->execute(['slr_a']));
    }

    /**
     * @param string $sourceCode
     * @param int $stockId
     * @return StockSourceLinkInterface|MockObject
     */
    private function link(string $sourceCode, int $stockId)
    {
        $link = $this->createMock(StockSourceLinkInterface::class);
        $link->method('getSourceCode')->willReturn($sourceCode);
        $link->method('getStockId')->willReturn($stockId);

        return $link;
    }
}
