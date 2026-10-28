<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Test\Unit\Model\Cache;

use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryStockVisualizer\Model\Cache\ResolveStockIdsBySourceCodes;
use Magento\InventoryStockVisualizer\Model\Cache\SourceItemDeltaBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @see SourceItemDeltaBuilder
 */
class SourceItemDeltaBuilderTest extends TestCase
{
    /**
     * @var ResolveStockIdsBySourceCodes|MockObject
     */
    private $resolveStockIds;

    /**
     * @var SourceItemDeltaBuilder
     */
    private $model;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->resolveStockIds = $this->createMock(ResolveStockIdsBySourceCodes::class);
        $this->model = new SourceItemDeltaBuilder($this->resolveStockIds);
    }

    /**
     * A save expands each source to every linked stock and keeps the signed per-source delta.
     *
     * @return void
     */
    public function testSaveBuildsDeltasAcrossLinkedStocks(): void
    {
        $this->resolveStockIds->method('execute')->with(['slr_a'])->willReturn(['slr_a' => [10, 30]]);

        $deltas = $this->model->build(
            [$this->sourceItem('SKU-1', 'slr_a', 4.0)],
            ['SKU-1|slr_a' => 10.0]
        );

        $expected = [
            10 => ['SKU-1' => ['total' => -6.0, 'bySource' => ['slr_a' => -6.0]]],
            30 => ['SKU-1' => ['total' => -6.0, 'bySource' => ['slr_a' => -6.0]]],
        ];
        $this->assertEquals($expected, $deltas);
    }

    /**
     * An unchanged quantity contributes no delta.
     *
     * @return void
     */
    public function testUnchangedQuantityIsSkipped(): void
    {
        $this->resolveStockIds->expects($this->never())->method('execute');

        $deltas = $this->model->build(
            [$this->sourceItem('SKU-1', 'slr_a', 5.0)],
            ['SKU-1|slr_a' => 5.0]
        );

        $this->assertSame([], $deltas);
    }

    /**
     * A delete treats the new quantity as zero.
     *
     * @return void
     */
    public function testDeleteTreatsNewQuantityAsZero(): void
    {
        $this->resolveStockIds->method('execute')->with(['slr_a'])->willReturn(['slr_a' => [10]]);

        $deltas = $this->model->build(
            [$this->sourceItem('SKU-1', 'slr_a', 0.0)],
            ['SKU-1|slr_a' => 7.0],
            true
        );

        $expected = [10 => ['SKU-1' => ['total' => -7.0, 'bySource' => ['slr_a' => -7.0]]]];
        $this->assertEquals($expected, $deltas);
    }

    /**
     * @param string $sku
     * @param string $sourceCode
     * @param float $qty
     * @return SourceItemInterface|MockObject
     */
    private function sourceItem(string $sku, string $sourceCode, float $qty)
    {
        $item = $this->createMock(SourceItemInterface::class);
        $item->method('getSku')->willReturn($sku);
        $item->method('getSourceCode')->willReturn($sourceCode);
        $item->method('getQuantity')->willReturn($qty);

        return $item;
    }
}
