<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Test\Unit\Model\Cache;

use Magento\InventoryReservationsApi\Model\ReservationInterface;
use Magento\InventoryStockVisualizer\Model\Cache\DispatchPurge;
use Magento\InventoryStockVisualizer\Model\Cache\PurgeOnReservations;
use Magento\InventoryStockVisualizer\Model\Cache\ResolveSkusToPurge;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @see PurgeOnReservations
 */
class PurgeOnReservationsTest extends TestCase
{
    private const SKU = 'SLR-1';
    private const STOCK_ID = 10;

    /**
     * @var ResolveSkusToPurge|MockObject
     */
    private $resolveSkusToPurge;

    /**
     * @var DispatchPurge|MockObject
     */
    private $dispatchPurge;

    /**
     * @var PurgeOnReservations
     */
    private $model;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->resolveSkusToPurge = $this->createMock(ResolveSkusToPurge::class);
        $this->dispatchPurge = $this->createMock(DispatchPurge::class);

        $this->model = new PurgeOnReservations($this->resolveSkusToPurge, $this->dispatchPurge);
    }

    /**
     * Empty or zero-quantity reservations dispatch nothing.
     *
     * @return void
     */
    public function testNoDeltasDoesNothing(): void
    {
        $this->resolveSkusToPurge->expects($this->never())->method('execute');
        $this->dispatchPurge->expects($this->never())->method('execute');

        $this->model->execute([$this->reservation(0.0)]);
    }

    /**
     * Grouped deltas are handed to the decider and its result to the dispatcher.
     *
     * @return void
     */
    public function testGroupsDeltasAndDispatchesResolvedSkus(): void
    {
        $expectedDeltas = [
            self::STOCK_ID => [self::SKU => ['total' => -3.0, 'bySource' => ['slr_a' => -3.0]]],
        ];
        $this->resolveSkusToPurge->expects($this->once())
            ->method('execute')
            ->with($expectedDeltas)
            ->willReturn([self::SKU]);
        $this->dispatchPurge->expects($this->once())->method('execute')->with([self::SKU]);

        $this->model->execute([
            $this->reservation(-1.0, 'slr_a'),
            $this->reservation(-2.0, 'slr_a'),
        ]);
    }

    /**
     * @param float $quantity
     * @param string|null $sourceCode
     * @return ReservationInterface|MockObject
     */
    private function reservation(float $quantity, ?string $sourceCode = null)
    {
        $reservation = $this->createMock(ReservationInterface::class);
        $reservation->method('getQuantity')->willReturn($quantity);
        $reservation->method('getStockId')->willReturn(self::STOCK_ID);
        $reservation->method('getSku')->willReturn(self::SKU);
        $reservation->method('getSourceCode')->willReturn($sourceCode);

        return $reservation;
    }
}
