<?php
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryReservations\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;
use Magento\InventoryReservationsApi\Model\ReservationInterface;
use Magento\InventoryReservationsApi\Model\GetReservationsQuantityInterface;
use Magento\InventoryReservationsApi\Model\SourceReservationsConfig;

/**
 * @inheritdoc
 */
class GetReservationsQuantity implements GetReservationsQuantityInterface
{
    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * @param ResourceConnection $resource
     * @param SourceReservationsConfig $sourceReservationsConfig
     * @param GetSourceAggregatedReservationsQuantity $getSourceAggregatedReservationsQuantity
     */
    public function __construct(
        ResourceConnection $resource,
        private readonly SourceReservationsConfig $sourceReservationsConfig,
        private readonly GetSourceAggregatedReservationsQuantity $getSourceAggregatedReservationsQuantity
    ) {
        $this->resource = $resource;
    }

    /**
     * @inheritdoc
     */
    public function execute(string $sku, int $stockId): float
    {
        if ($this->sourceReservationsConfig->isEnabled()) {
            return $this->getSourceAggregatedReservationsQuantity->execute([$sku], $stockId)[$sku] ?? 0.0;
        }

        $connection = $this->resource->getConnection();
        $reservationTable = $this->resource->getTableName('inventory_reservation');

        $select = $connection->select()
            ->from($reservationTable, [ReservationInterface::QUANTITY => 'SUM(' . ReservationInterface::QUANTITY . ')'])
            ->where(ReservationInterface::SKU . ' = ?', $sku)
            ->where(ReservationInterface::STOCK_ID . ' = ?', $stockId)
            ->limit(1);

        $reservationQty = $connection->fetchOne($select);
        if (false === $reservationQty) {
            $reservationQty = 0;
        }
        return (float)$reservationQty;
    }
}
