<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Test\Unit\Model\SourceReservation;

use Magento\InventoryReservationsApi\Model\SourceReservationsConfig;
use Magento\InventorySales\Model\PlaceReservationsForSalesEvent;
use Magento\InventorySales\Model\SourceReservation\PlaceReservationsForSalesEventRouter;
use Magento\InventorySales\Model\SourceReservation\PlaceSourceAwareReservationsForSalesEvent;
use Magento\InventorySalesApi\Api\Data\ItemToSellInterface;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\InventorySalesApi\Api\Data\SalesEventInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PlaceReservationsForSalesEventRouterTest extends TestCase
{
    /**
     * @var PlaceReservationsForSalesEvent|MockObject
     */
    private $legacyPlaceReservations;

    /**
     * @var PlaceSourceAwareReservationsForSalesEvent|MockObject
     */
    private $sourceAwarePlaceReservations;

    /**
     * @var SourceReservationsConfig|MockObject
     */
    private $sourceReservationsConfig;

    /**
     * @var PlaceReservationsForSalesEventRouter
     */
    private $router;

    protected function setUp(): void
    {
        $this->legacyPlaceReservations = $this->createMock(PlaceReservationsForSalesEvent::class);
        $this->sourceAwarePlaceReservations = $this->createMock(PlaceSourceAwareReservationsForSalesEvent::class);
        $this->sourceReservationsConfig = $this->createMock(SourceReservationsConfig::class);

        $this->router = new PlaceReservationsForSalesEventRouter(
            $this->legacyPlaceReservations,
            $this->sourceAwarePlaceReservations,
            $this->sourceReservationsConfig
        );
    }

    public function testDelegatesToLegacyImplementationWhenFlagIsOff(): void
    {
        $items = [$this->createMock(ItemToSellInterface::class)];
        $salesChannel = $this->createMock(SalesChannelInterface::class);
        $salesEvent = $this->createMock(SalesEventInterface::class);

        $this->sourceReservationsConfig->method('isEnabled')->willReturn(false);
        $this->legacyPlaceReservations
            ->expects(self::once())
            ->method('execute')
            ->with($items, $salesChannel, $salesEvent);
        $this->sourceAwarePlaceReservations->expects(self::never())->method('execute');

        $this->router->execute($items, $salesChannel, $salesEvent);
    }

    public function testDelegatesToSourceAwareImplementationWhenFlagIsOn(): void
    {
        $items = [$this->createMock(ItemToSellInterface::class)];
        $salesChannel = $this->createMock(SalesChannelInterface::class);
        $salesEvent = $this->createMock(SalesEventInterface::class);

        $this->sourceReservationsConfig->method('isEnabled')->willReturn(true);
        $this->sourceAwarePlaceReservations
            ->expects(self::once())
            ->method('execute')
            ->with($items, $salesChannel, $salesEvent);
        $this->legacyPlaceReservations->expects(self::never())->method('execute');

        $this->router->execute($items, $salesChannel, $salesEvent);
    }
}
