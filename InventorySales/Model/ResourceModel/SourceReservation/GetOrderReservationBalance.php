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
 * Load the reservation balance of an order keyed by its increment id, taken from
 * the dedicated column when present and from the serialized metadata otherwise.
 * The increment id is the identifier shared by every sales event of an order
 * (placement, shipment, cancel, credit memo); the metadata object_id is not, so
 * it cannot be used to net a compensation against its original demand.
 */
class GetOrderReservationBalance
{
    /**
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /**
     * Get the reservation balance indexed by SKU and source code ('' for rows without a source).
     *
     * @param string $objectIncrementId
     * @param string[] $skus
     * @param int $stockId
     * @return array<string, array<string, float>> [sku][source_code|''] => SUM(quantity)
     */
    public function execute(string $objectIncrementId, array $skus, int $stockId): array
    {
        if (empty($skus) || $objectIncrementId === '') {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();
        $incrementIdExpr = sprintf(
            "COALESCE(%s, JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.object_increment_id')))",
            ReservationInterface::OBJECT_INCREMENT_ID
        );
        $select = $connection->select()
            ->from(
                $this->resourceConnection->getTableName('inventory_reservation'),
                [
                    ReservationInterface::SKU,
                    ReservationInterface::SOURCE_CODE,
                    'quantity' => 'SUM(' . ReservationInterface::QUANTITY . ')',
                ]
            )
            ->where(ReservationInterface::STOCK_ID . ' = ?', $stockId)
            ->where(ReservationInterface::SKU . ' IN (?)', $skus)
            ->where($incrementIdExpr . ' = ?', $objectIncrementId)
            ->group([ReservationInterface::SKU, ReservationInterface::SOURCE_CODE]);

        $result = [];
        foreach ($connection->fetchAll($select) as $row) {
            $result[$row[ReservationInterface::SKU]][(string)$row[ReservationInterface::SOURCE_CODE]] =
                (float)$row['quantity'];
        }

        return $result;
    }
}
