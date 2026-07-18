<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Model\ResourceModel\SourceReservation;

use Magento\Framework\App\ResourceConnection;
use Magento\InventoryReservationsApi\Model\ReservationInterface;

/**
 * Aggregate the reservation balance of the given SKUs per source, across all stocks.
 */
class GetReservationsQuantityBySkusAndSources
{
    /**
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /**
     * Get the summed reservation quantity indexed by source code and SKU.
     *
     * @param string[] $skus
     * @param string[] $sourceCodes
     * @return array<string, array<string, float>> [source_code][sku] => SUM(quantity)
     */
    public function execute(array $skus, array $sourceCodes): array
    {
        if (empty($skus) || empty($sourceCodes)) {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from(
                $this->resourceConnection->getTableName('inventory_reservation'),
                [
                    ReservationInterface::SOURCE_CODE,
                    ReservationInterface::SKU,
                    'quantity' => 'SUM(' . ReservationInterface::QUANTITY . ')',
                ]
            )
            ->where(ReservationInterface::SOURCE_CODE . ' IN (?)', $sourceCodes)
            ->where(ReservationInterface::SKU . ' IN (?)', $skus)
            ->group([ReservationInterface::SOURCE_CODE, ReservationInterface::SKU]);

        $result = [];
        foreach ($connection->fetchAll($select) as $row) {
            $result[$row[ReservationInterface::SOURCE_CODE]][$row[ReservationInterface::SKU]] =
                (float)$row['quantity'];
        }

        return $result;
    }
}
