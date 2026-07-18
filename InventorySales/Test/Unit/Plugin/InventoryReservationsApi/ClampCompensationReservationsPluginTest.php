<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Test\Unit\Plugin\InventoryReservationsApi;

use Magento\Framework\Serialize\SerializerInterface;
use Magento\InventoryReservationsApi\Model\AppendReservationsInterface;
use Magento\InventoryReservationsApi\Model\ReservationBuilderInterface;
use Magento\InventoryReservationsApi\Model\ReservationInterface;
use Magento\InventorySales\Model\ResourceModel\SourceReservation\GetOrderReservationBalance;
use Magento\InventorySales\Model\ResourceModel\SourceReservation\ReservationClampLock;
use Magento\InventorySales\Plugin\InventoryReservationsApi\ClampCompensationReservationsPlugin;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ClampCompensationReservationsPluginTest extends TestCase
{
    private const STOCK_ID = 10;

    /**
     * @var GetOrderReservationBalance|MockObject
     */
    private $getOrderReservationBalance;

    /**
     * @var ReservationBuilderInterface|MockObject
     */
    private $reservationBuilder;

    /**
     * @var AppendReservationsInterface|MockObject
     */
    private $subject;

    /**
     * @var ClampCompensationReservationsPlugin
     */
    private $plugin;

    /**
     * @var ReservationInterface[]|null
     */
    private $appended;

    /**
     * @var bool
     */
    private $proceedCalled;

    protected function setUp(): void
    {
        $this->getOrderReservationBalance = $this->createMock(GetOrderReservationBalance::class);
        $this->reservationBuilder = $this->createMock(ReservationBuilderInterface::class);
        $this->reservationBuilder->method('setSku')->willReturnSelf();
        $this->reservationBuilder->method('setQuantity')->willReturnSelf();
        $this->reservationBuilder->method('setStockId')->willReturnSelf();
        $this->reservationBuilder->method('setMetadata')->willReturnSelf();
        $this->reservationBuilder->method('setSourceCode')->willReturnSelf();
        $this->reservationBuilder->method('setObjectIncrementId')->willReturnSelf();
        $this->reservationBuilder->method('build')
            ->willReturn($this->createMock(ReservationInterface::class));

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->method('unserialize')
            ->willReturnCallback(static fn (string $value) => json_decode($value, true) ?? []);

        $this->subject = $this->createMock(AppendReservationsInterface::class);
        $this->appended = null;
        $this->proceedCalled = false;

        $clampLock = $this->createMock(ReservationClampLock::class);
        $clampLock->method('acquire')->willReturn([]);

        $this->plugin = new ClampCompensationReservationsPlugin(
            $this->getOrderReservationBalance,
            $this->reservationBuilder,
            $serializer,
            $this->createMock(LoggerInterface::class),
            $clampLock
        );
    }

    public function testPassesThroughDemandReservations(): void
    {
        $this->getOrderReservationBalance->expects(self::never())->method('execute');
        $this->reservationBuilder->expects(self::never())->method('build');

        $reservations = [$this->reservation(-5.0, 'sku-1', 'source-a')];
        $this->invokePlugin($reservations);

        self::assertSame($reservations, $this->appended);
    }

    public function testAllowsReleaseWithinOutstandingBalance(): void
    {
        $this->givenBalance(['sku-1' => ['source-a' => -5.0]]);
        $this->reservationBuilder->expects(self::never())->method('build');

        $reservations = [$this->reservation(5.0, 'sku-1', 'source-a')];
        $this->invokePlugin($reservations);

        self::assertSame($reservations, $this->appended);
    }

    public function testClampsReleaseExceedingOutstandingBalance(): void
    {
        $this->givenBalance(['sku-1' => ['source-a' => -5.0]]);
        $this->reservationBuilder->expects(self::once())->method('setQuantity')->with(5.0)->willReturnSelf();

        $this->invokePlugin([$this->reservation(8.0, 'sku-1', 'source-a')]);

        self::assertCount(1, $this->appended);
    }

    public function testDropsReleaseWithNoOutstandingBalance(): void
    {
        $this->givenBalance(['sku-1' => ['source-a' => 0.0]]);
        $this->reservationBuilder->expects(self::never())->method('build');

        $this->invokePlugin([$this->reservation(5.0, 'sku-1', 'source-a')]);

        self::assertFalse($this->proceedCalled);
    }

    public function testMultipleReleasesShareTheOutstandingBalance(): void
    {
        $this->givenBalance(['sku-1' => ['source-a' => -5.0]]);
        $this->reservationBuilder->expects(self::once())->method('setQuantity')->with(2.0)->willReturnSelf();

        $this->invokePlugin([
            $this->reservation(3.0, 'sku-1', 'source-a'),
            $this->reservation(3.0, 'sku-1', 'source-a'),
        ]);

        self::assertCount(2, $this->appended);
    }

    public function testPassesThroughWhenMetadataHasNoOrderContext(): void
    {
        $this->getOrderReservationBalance->expects(self::never())->method('execute');

        $reservation = $this->createMock(ReservationInterface::class);
        $reservation->method('getQuantity')->willReturn(5.0);
        $reservation->method('getMetadata')->willReturn('');
        $reservations = [$reservation];
        $this->invokePlugin($reservations);

        self::assertSame($reservations, $this->appended);
    }

    public function testClampsAgainstStockScopedBalanceForNullSource(): void
    {
        $this->givenBalance(['sku-1' => ['' => -4.0]]);
        $this->reservationBuilder->expects(self::once())->method('setQuantity')->with(4.0)->willReturnSelf();

        $this->invokePlugin([$this->reservation(6.0, 'sku-1', null)]);

        self::assertCount(1, $this->appended);
    }

    /**
     * @param array<string, array<string, float>> $balance
     */
    private function givenBalance(array $balance): void
    {
        $this->getOrderReservationBalance->method('execute')->willReturn($balance);
    }

    /**
     * @param ReservationInterface[] $reservations
     */
    private function invokePlugin(array $reservations): void
    {
        $proceed = function (array $appended) {
            $this->proceedCalled = true;
            $this->appended = $appended;
        };
        $this->plugin->aroundExecute($this->subject, $proceed, $reservations);
    }

    private function reservation(float $qty, string $sku, ?string $source): ReservationInterface
    {
        $reservation = $this->createMock(ReservationInterface::class);
        $reservation->method('getQuantity')->willReturn($qty);
        $reservation->method('getSku')->willReturn($sku);
        $reservation->method('getStockId')->willReturn(self::STOCK_ID);
        $reservation->method('getSourceCode')->willReturn($source);
        $reservation->method('getObjectIncrementId')->willReturn('000000123');
        $reservation->method('getMetadata')->willReturn(
            json_encode(['object_type' => 'order', 'object_id' => '123', 'object_increment_id' => '000000123'])
        );

        return $reservation;
    }
}
