<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Test\Unit\Model\SourceReservation;

use Magento\Framework\Serialize\SerializerInterface;
use Magento\InventoryReservationsApi\Model\AppendReservationsInterface;
use Magento\InventoryReservationsApi\Model\ReservationBuilderInterface;
use Magento\InventoryReservationsApi\Model\ReservationInterface;
use Magento\InventorySales\Model\ResourceModel\SourceReservation\GetOrderReservationLedger;
use Magento\InventorySales\Model\SourceReservation\ReconcileOrderReservations;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ReconcileOrderReservationsTest extends TestCase
{
    private const OBJECT_ID = 42;
    private const INCREMENT_ID = '000000042';

    /**
     * @var GetOrderReservationLedger|MockObject
     */
    private $getOrderReservationLedger;

    /**
     * @var AppendReservationsInterface|MockObject
     */
    private $appendReservations;

    /**
     * @var ReconcileOrderReservations
     */
    private $model;

    protected function setUp(): void
    {
        $this->getOrderReservationLedger = $this->createMock(GetOrderReservationLedger::class);
        $this->appendReservations = $this->createMock(AppendReservationsInterface::class);

        $reservationBuilder = $this->createMock(ReservationBuilderInterface::class);
        $reservationBuilder->method('setSku')->willReturnSelf();
        $reservationBuilder->method('setQuantity')->willReturnSelf();
        $reservationBuilder->method('setStockId')->willReturnSelf();
        $reservationBuilder->method('setMetadata')->willReturnSelf();
        $reservationBuilder->method('setSourceCode')->willReturnSelf();
        $reservationBuilder->method('setObjectIncrementId')->willReturnSelf();
        $reservationBuilder->method('build')->willReturnCallback(
            fn () => $this->createMock(ReservationInterface::class)
        );

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->method('serialize')->willReturnCallback(static fn ($value) => json_encode($value));

        $this->model = new ReconcileOrderReservations(
            $this->getOrderReservationLedger,
            $this->appendReservations,
            $reservationBuilder,
            $serializer
        );
    }

    public function testSkipsNonTerminalOrder(): void
    {
        $this->getOrderReservationLedger->expects(self::never())->method('execute');
        $this->appendReservations->expects(self::never())->method('execute');

        $result = $this->model->execute(self::OBJECT_ID, self::INCREMENT_ID, 'processing');

        self::assertSame([], $result);
    }

    public function testSkipsWhenNoNegativeBalance(): void
    {
        $this->getOrderReservationLedger->method('execute')->willReturn([]);
        $this->appendReservations->expects(self::never())->method('execute');

        $result = $this->model->execute(self::OBJECT_ID, self::INCREMENT_ID, Order::STATE_COMPLETE);

        self::assertSame([], $result);
    }

    public function testReleasesNegativeBalancePerSource(): void
    {
        $this->getOrderReservationLedger->method('execute')->willReturn([
            ['stock_id' => 2, 'sku' => 'SLR-1', 'source_code' => 'slr_a', 'balance' => -3.0],
            ['stock_id' => 2, 'sku' => 'SLR-1', 'source_code' => 'slr_b', 'balance' => -2.0],
        ]);
        $this->appendReservations->expects(self::once())->method('execute')
            ->with(self::countOf(2));

        $result = $this->model->execute(self::OBJECT_ID, self::INCREMENT_ID, Order::STATE_CANCELED);

        self::assertSame(
            [
                ['stock_id' => 2, 'sku' => 'SLR-1', 'source_code' => 'slr_a', 'quantity' => 3.0],
                ['stock_id' => 2, 'sku' => 'SLR-1', 'source_code' => 'slr_b', 'quantity' => 2.0],
            ],
            $result
        );
    }

    public function testDryRunPlansWithoutAppending(): void
    {
        $this->getOrderReservationLedger->method('execute')->willReturn([
            ['stock_id' => 2, 'sku' => 'SLR-1', 'source_code' => null, 'balance' => -4.0],
        ]);
        $this->appendReservations->expects(self::never())->method('execute');

        $result = $this->model->execute(self::OBJECT_ID, self::INCREMENT_ID, Order::STATE_CLOSED, true);

        self::assertSame(
            [['stock_id' => 2, 'sku' => 'SLR-1', 'source_code' => null, 'quantity' => 4.0]],
            $result
        );
    }

    public function testSkipsEmptyIncrementId(): void
    {
        $this->getOrderReservationLedger->expects(self::never())->method('execute');

        self::assertSame([], $this->model->execute(self::OBJECT_ID, '', Order::STATE_COMPLETE));
    }
}
