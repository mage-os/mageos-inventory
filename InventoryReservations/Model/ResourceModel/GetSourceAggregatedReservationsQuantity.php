<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryReservations\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;
use Magento\InventoryReservationsApi\Model\ReservationInterface;

/**
 * Aggregate reservations for a stock combining stock-scoped rows and rows of the sources linked to it.
 */
class GetSourceAggregatedReservationsQuantity
{
    /**
     * @param ResourceConnection $resource
     */
    public function __construct(
        private readonly ResourceConnection $resource
    ) {
    }

    /**
     * Get the summed reservation quantity per SKU for the given stock.
     *
     * @param string[] $skus
     * @param int $stockId
     * @return array<string, float>
     */
    public function execute(array $skus, int $stockId): array
    {
        $connection = $this->resource->getConnection();
        $reservationTable = $this->resource->getTableName('inventory_reservation');

        $stockScopedSelect = $connection->select()
            ->from(
                $reservationTable,
                [ReservationInterface::SKU, ReservationInterface::QUANTITY]
            )
            ->where(ReservationInterface::STOCK_ID . ' = ?', $stockId)
            ->where(ReservationInterface::SKU . ' IN (?)', $skus)
            ->where(ReservationInterface::SOURCE_CODE . ' IS NULL');

        $sourceScopedSelect = $connection->select()
            ->from(
                ['reservation' => $reservationTable],
                [ReservationInterface::SKU, ReservationInterface::QUANTITY]
            )
            ->joinInner(
                ['stock_source_link' => $this->resource->getTableName('inventory_source_stock_link')],
                'stock_source_link.source_code = reservation.' . ReservationInterface::SOURCE_CODE,
                []
            )
            ->joinInner(
                ['source' => $this->resource->getTableName('inventory_source')],
                'source.source_code = stock_source_link.source_code',
                []
            )
            ->where('stock_source_link.stock_id = ?', $stockId)
            ->where('source.enabled = ?', 1)
            ->where('reservation.' . ReservationInterface::SKU . ' IN (?)', $skus);

        $unionSelect = $connection->select()->union(
            [$stockScopedSelect, $sourceScopedSelect],
            Select::SQL_UNION_ALL
        );

        $select = $connection->select()
            ->from(
                ['reservations' => $unionSelect],
                [
                    ReservationInterface::SKU,
                    ReservationInterface::QUANTITY => 'SUM(' . ReservationInterface::QUANTITY . ')',
                ]
            )
            ->group(ReservationInterface::SKU);

        $result = $connection->fetchPairs($select);
        foreach ($skus as $sku) {
            if (!isset($result[$sku])) {
                $result[$sku] = 0;
            }
        }

        return array_map(static fn ($value) => (float)$value, $result);
    }
}
