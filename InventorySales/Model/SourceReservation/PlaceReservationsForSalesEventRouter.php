<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Model\SourceReservation;

use Magento\InventoryReservationsApi\Model\SourceReservationsConfig;
use Magento\InventorySales\Model\PlaceReservationsForSalesEvent;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\InventorySalesApi\Api\Data\SalesEventInterface;
use Magento\InventorySalesApi\Api\PlaceReservationsForSalesEventInterface;

/**
 * Route reservation placement to the legacy or the source-aware implementation based on the feature flag.
 */
class PlaceReservationsForSalesEventRouter implements PlaceReservationsForSalesEventInterface
{
    /**
     * @param PlaceReservationsForSalesEvent $legacyPlaceReservations
     * @param PlaceSourceAwareReservationsForSalesEvent $sourceAwarePlaceReservations
     * @param SourceReservationsConfig $sourceReservationsConfig
     */
    public function __construct(
        private readonly PlaceReservationsForSalesEvent $legacyPlaceReservations,
        private readonly PlaceSourceAwareReservationsForSalesEvent $sourceAwarePlaceReservations,
        private readonly SourceReservationsConfig $sourceReservationsConfig
    ) {
    }

    /**
     * @inheritdoc
     */
    public function execute(array $items, SalesChannelInterface $salesChannel, SalesEventInterface $salesEvent): void
    {
        if ($this->sourceReservationsConfig->isEnabled()) {
            $this->sourceAwarePlaceReservations->execute($items, $salesChannel, $salesEvent);
        } else {
            $this->legacyPlaceReservations->execute($items, $salesChannel, $salesEvent);
        }
    }
}
