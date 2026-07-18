<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Test\Unit\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;
use Magento\InventoryApi\Api\Data\SourceInterface;
use Magento\InventoryApi\Api\GetSourcesAssignedToStockOrderedByPriorityInterface;
use Magento\InventorySales\Model\ResourceModel\AcquireStockItemLocks;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test the deadlock-safe lock-name ordering of AcquireStockItemLocks.
 */
class AcquireStockItemLocksTest extends TestCase
{
    /**
     * @var AcquireStockItemLocks
     */
    private $model;

    /**
     * @var GetSourcesAssignedToStockOrderedByPriorityInterface|MockObject
     */
    private $getSourcesMock;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->getSourcesMock = $this->createMock(GetSourcesAssignedToStockOrderedByPriorityInterface::class);
        $this->model = new AcquireStockItemLocks(
            $this->createMock(ResourceConnection::class),
            $this->getSourcesMock
        );
    }

    /**
     * One lock name per (sku, enabled source); disabled sources are excluded.
     */
    public function testBuildsOneLockPerSkuAndEnabledSource(): void
    {
        $this->getSourcesMock->method('execute')->willReturn([
            $this->source('src_a', true),
            $this->source('src_b', true),
            $this->source('src_disabled', false),
        ]);

        $names = $this->model->buildOrderedLockNames(['sku1', 'sku2'], 5);

        // 2 skus x 2 enabled sources, none from the disabled source.
        $this->assertCount(4, $names);
        $this->assertSame(array_values(array_unique($names)), $names);
    }

    /**
     * The result is globally sorted and independent of the input ordering, which
     * is what guarantees concurrent acquisitions cannot deadlock.
     */
    public function testOrderingIsGlobalAndInputIndependent(): void
    {
        $this->getSourcesMock->method('execute')->willReturnCallback(
            function () {
                // Priority order differs from the global (sorted) order on purpose.
                return [$this->source('src_b', true), $this->source('src_a', true)];
            }
        );

        $forward = $this->model->buildOrderedLockNames(['sku1', 'sku2'], 5);
        $reversed = $this->model->buildOrderedLockNames(['sku2', 'sku1'], 5);

        $sorted = $forward;
        sort($sorted, SORT_STRING);
        $this->assertSame($sorted, $forward, 'lock names must be globally sorted');
        $this->assertSame($forward, $reversed, 'ordering must not depend on the input sku order');
    }

    /**
     * With no enabled sources the lock falls back to a per-stock name.
     */
    public function testFallsBackToStockLockWhenNoEnabledSources(): void
    {
        $this->getSourcesMock->method('execute')->willReturn([$this->source('src_a', false)]);

        $names = $this->model->buildOrderedLockNames(['sku1'], 7);

        $this->assertCount(1, $names);
        $this->assertStringStartsWith('inv_stk_7_', $names[0]);
    }

    /**
     * Two stocks sharing a source produce the SAME lock name for that source, so
     * cross-stock orders serialise on it.
     */
    public function testSharedSourceProducesSameLockNameAcrossStocks(): void
    {
        $this->getSourcesMock->method('execute')->willReturnCallback(
            function (int $stockId) {
                return $stockId === 5
                    ? [$this->source('shared', true), $this->source('only_5', true)]
                    : [$this->source('shared', true)];
            }
        );

        $stock5 = $this->model->buildOrderedLockNames(['sku1'], 5);
        $stock6 = $this->model->buildOrderedLockNames(['sku1'], 6);

        $shared = array_values(array_intersect($stock5, $stock6));
        $this->assertCount(1, $shared, 'the shared source must yield a common lock name');
    }

    /**
     * @param string $code
     * @param bool $enabled
     * @return SourceInterface|MockObject
     */
    private function source(string $code, bool $enabled)
    {
        $source = $this->createMock(SourceInterface::class);
        $source->method('getSourceCode')->willReturn($code);
        $source->method('isEnabled')->willReturn($enabled);

        return $source;
    }
}
