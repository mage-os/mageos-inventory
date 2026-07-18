<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Test\Unit\Model\SourceReservation;

use Magento\InventoryIndexer\Indexer\Stock\StockIndexer;
use Magento\InventorySales\Model\ResourceModel\SourceReservation\GetTerminalOrdersWithResidualReservations;
use Magento\InventorySales\Model\SourceReservation\ReconcileOrderReservations;
use Magento\InventorySales\Model\SourceReservation\ReconcileReservationsSweep;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ReconcileReservationsSweepTest extends TestCase
{
    /**
     * @var GetTerminalOrdersWithResidualReservations|MockObject
     */
    private $getTerminalOrders;

    /**
     * @var ReconcileOrderReservations|MockObject
     */
    private $engine;

    /**
     * @var StockIndexer|MockObject
     */
    private $stockIndexer;

    /**
     * @var ReconcileReservationsSweep
     */
    private $sweep;

    protected function setUp(): void
    {
        $this->getTerminalOrders = $this->createMock(GetTerminalOrdersWithResidualReservations::class);
        $this->engine = $this->createMock(ReconcileOrderReservations::class);
        $this->stockIndexer = $this->createMock(StockIndexer::class);
        $this->sweep = new ReconcileReservationsSweep(
            $this->getTerminalOrders,
            $this->engine,
            $this->stockIndexer
        );
    }

    public function testReconcilesEachOrderAndReindexesAffectedStocks(): void
    {
        $this->getTerminalOrders->method('execute')->willReturn([
            ['object_id' => 1, 'increment_id' => '000000001', 'state' => Order::STATE_COMPLETE],
            ['object_id' => 2, 'increment_id' => '000000002', 'state' => Order::STATE_CANCELED],
        ]);
        $this->engine->method('execute')->willReturnOnConsecutiveCalls(
            [['stock_id' => 2, 'sku' => 'A', 'source_code' => 'slr_a', 'quantity' => 3.0]],
            [['stock_id' => 3, 'sku' => 'A', 'source_code' => 'slr_a', 'quantity' => 1.0]]
        );
        $this->stockIndexer->expects(self::once())->method('executeList')->with([2, 3]);

        $result = $this->sweep->execute(500);

        self::assertSame(2, $result['orders']);
        self::assertSame(2, $result['compensations']);
        self::assertSame([2, 3], $result['stock_ids']);
        self::assertFalse($result['limit_reached']);
    }

    public function testDryRunDoesNotReindex(): void
    {
        $this->getTerminalOrders->method('execute')->willReturn([
            ['object_id' => 1, 'increment_id' => '000000001', 'state' => Order::STATE_COMPLETE],
        ]);
        $this->engine->expects(self::once())->method('execute')
            ->with(1, '000000001', Order::STATE_COMPLETE, true)
            ->willReturn([['stock_id' => 2, 'sku' => 'A', 'source_code' => null, 'quantity' => 4.0]]);
        $this->stockIndexer->expects(self::never())->method('executeList');

        $result = $this->sweep->execute(500, true);

        self::assertSame(1, $result['compensations']);
    }

    public function testLimitReachedWhenBatchIsFull(): void
    {
        $this->getTerminalOrders->method('execute')->willReturn([
            ['object_id' => 1, 'increment_id' => '000000001', 'state' => Order::STATE_COMPLETE],
        ]);
        $this->engine->method('execute')->willReturn([]);

        $result = $this->sweep->execute(1);

        self::assertTrue($result['limit_reached']);
    }
}
