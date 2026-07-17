<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Test\Unit\Plugin\InventoryApi;

use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\SourceItemsSaveInterface;
use Magento\InventoryStockVisualizer\Model\Cache\DispatchPurge;
use Magento\InventoryStockVisualizer\Model\Cache\ResolveSkusToPurge;
use Magento\InventoryStockVisualizer\Model\Cache\SnapshotSourceItemQty;
use Magento\InventoryStockVisualizer\Model\Cache\SourceItemDeltaBuilder;
use Magento\InventoryStockVisualizer\Model\Config;
use Magento\InventoryStockVisualizer\Plugin\InventoryApi\PurgeOnSourceItemsSave;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @see PurgeOnSourceItemsSave
 */
class PurgeOnSourceItemsSaveTest extends TestCase
{
    /**
     * @var Config|MockObject
     */
    private $config;

    /**
     * @var SnapshotSourceItemQty|MockObject
     */
    private $snapshot;

    /**
     * @var SourceItemDeltaBuilder|MockObject
     */
    private $deltaBuilder;

    /**
     * @var ResolveSkusToPurge|MockObject
     */
    private $resolveSkusToPurge;

    /**
     * @var DispatchPurge|MockObject
     */
    private $dispatchPurge;

    /**
     * @var SourceItemsSaveInterface|MockObject
     */
    private $subject;

    /**
     * @var PurgeOnSourceItemsSave
     */
    private $plugin;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->config = $this->createMock(Config::class);
        $this->snapshot = $this->createMock(SnapshotSourceItemQty::class);
        $this->deltaBuilder = $this->createMock(SourceItemDeltaBuilder::class);
        $this->resolveSkusToPurge = $this->createMock(ResolveSkusToPurge::class);
        $this->dispatchPurge = $this->createMock(DispatchPurge::class);
        $this->subject = $this->createMock(SourceItemsSaveInterface::class);

        $this->plugin = new PurgeOnSourceItemsSave(
            $this->config,
            $this->snapshot,
            $this->deltaBuilder,
            $this->resolveSkusToPurge,
            $this->dispatchPurge,
            $this->createMock(LoggerInterface::class)
        );
    }

    /**
     * The snapshot feeds the delta builder and resolved SKUs are dispatched.
     *
     * @return void
     */
    public function testSnapshotsBeforeAndDispatchesAfter(): void
    {
        $items = [$this->createMock(SourceItemInterface::class)];
        $snapshot = ['SKU-1|slr_a' => 5.0];
        $deltas = [10 => ['SKU-1' => ['total' => -2.0, 'bySource' => ['slr_a' => -2.0]]]];

        $this->config->method('isEnabled')->willReturn(true);
        $this->snapshot->expects($this->once())->method('execute')->with($items)->willReturn($snapshot);
        $this->deltaBuilder->expects($this->once())->method('build')->with($items, $snapshot)->willReturn($deltas);
        $this->resolveSkusToPurge->expects($this->once())->method('execute')->with($deltas)->willReturn(['SKU-1']);
        $this->dispatchPurge->expects($this->once())->method('execute')->with(['SKU-1']);

        $this->plugin->beforeExecute($this->subject, $items);
        $this->assertNull($this->plugin->afterExecute($this->subject, null, $items));
    }

    /**
     * When disabled nothing is snapshotted or dispatched.
     *
     * @return void
     */
    public function testDisabledDoesNothing(): void
    {
        $items = [$this->createMock(SourceItemInterface::class)];
        $this->config->method('isEnabled')->willReturn(false);
        $this->snapshot->expects($this->never())->method('execute');
        $this->deltaBuilder->expects($this->never())->method('build');
        $this->dispatchPurge->expects($this->never())->method('execute');

        $this->plugin->beforeExecute($this->subject, $items);
        $this->plugin->afterExecute($this->subject, null, $items);
    }

    /**
     * No resolved SKUs means no dispatch.
     *
     * @return void
     */
    public function testNoDeltasDoesNotDispatch(): void
    {
        $items = [$this->createMock(SourceItemInterface::class)];
        $this->config->method('isEnabled')->willReturn(true);
        $this->snapshot->method('execute')->willReturn([]);
        $this->deltaBuilder->method('build')->willReturn([]);
        $this->resolveSkusToPurge->expects($this->never())->method('execute');
        $this->dispatchPurge->expects($this->never())->method('execute');

        $this->plugin->beforeExecute($this->subject, $items);
        $this->plugin->afterExecute($this->subject, null, $items);
    }
}
