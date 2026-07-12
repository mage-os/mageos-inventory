<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventorySales\Model\ResourceModel\SourceReservation;

use Magento\Framework\App\ResourceConnection;
use Magento\InventoryReservationsApi\Model\ReservationInterface;

/**
 * Load the per-source reservation balance of a sales event object.
 */
class GetPendingSourceReservations
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
        if (empty($skus)) {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from(
                $this->resourceConnection->getTableName('inventory_reservation'),
                [
                    ReservationInterface::SKU,
                    ReservationInterface::SOURCE_CODE,
                    'quantity' => 'SUM(' . ReservationInterface::QUANTITY . ')',
                ]
            )
            ->where(ReservationInterface::OBJECT_INCREMENT_ID . ' = ?', $objectIncrementId)
            ->where(ReservationInterface::SKU . ' IN (?)', $skus)
            ->where(ReservationInterface::STOCK_ID . ' = ?', $stockId)
            ->group([ReservationInterface::SKU, ReservationInterface::SOURCE_CODE]);

        $result = [];
        foreach ($connection->fetchAll($select) as $row) {
            $result[$row[ReservationInterface::SKU]][(string)$row[ReservationInterface::SOURCE_CODE]] =
                (float)$row['quantity'];
        }

        return $result;
    }
}
