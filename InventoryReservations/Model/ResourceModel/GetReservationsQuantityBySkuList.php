<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryReservations\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;
use Magento\InventoryReservationsApi\Model\GetReservationsQuantityBySkuListInterface;
use Magento\InventoryReservationsApi\Model\ReservationInterface;
use Magento\InventoryReservationsApi\Model\SourceReservationsConfig;

class GetReservationsQuantityBySkuList implements GetReservationsQuantityBySkuListInterface
{
    /**
     * @param ResourceConnection $resource
     * @param SourceReservationsConfig $sourceReservationsConfig
     * @param GetSourceAggregatedReservationsQuantity $getSourceAggregatedReservationsQuantity
     */
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly SourceReservationsConfig $sourceReservationsConfig,
        private readonly GetSourceAggregatedReservationsQuantity $getSourceAggregatedReservationsQuantity
    ) {
    }

    /**
     * @inheritdoc
     */
    public function execute(array $skus, int $stockId): array
    {
        if ($this->sourceReservationsConfig->isEnabled()) {
            return $this->getSourceAggregatedReservationsQuantity->execute($skus, $stockId);
        }

        $connection = $this->resource->getConnection();
        $reservationTable = $this->resource->getTableName('inventory_reservation');

        $select = $connection->select()
            ->from(
                $reservationTable,
                [
                    ReservationInterface::SKU,
                    ReservationInterface::QUANTITY => 'SUM(' . ReservationInterface::QUANTITY . ')'
                ]
            )
            ->where(ReservationInterface::STOCK_ID . ' = ?', $stockId)
            ->where(ReservationInterface::SKU . ' IN (?)', $skus)
            ->group([ReservationInterface::STOCK_ID, ReservationInterface::SKU]);

        $result = $connection->fetchPairs($select);
        foreach ($skus as $sku) {
            if (!isset($result[$sku])) {
                $result[$sku] = 0;
            }
        }
        return array_map(fn ($value) => (float) $value, $result);
    }
}
