<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventorySales\Model\ResourceModel\SourceReservation;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Sql\Expression;
use Magento\Inventory\Model\ResourceModel\SourceItem;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryReservationsApi\Model\ReservationInterface;

/**
 * Vector-agnostic detector of oversold supply: source/SKU positions whose
 * in-stock physical quantity is below the reservations committed against that
 * source, across all orders. Independent of how the physical qty was lowered, so
 * it also catches direct database edits that no write-path hook can observe.
 */
class GetOversoldSourceItems
{
    private const EPSILON = 0.000001;

    /**
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /**
     * Return the oversold positions, bounded by the given limit.
     *
     * @param int $limit
     * @return array<int, array{source_code:string, sku:string, physical:float, reserved:float, delta:float}>
     */
    public function execute(int $limit): array
    {
        $connection = $this->resourceConnection->getConnection();
        $reservationTable = $this->resourceConnection->getTableName('inventory_reservation');
        $sourceItemTable = $this->resourceConnection->getTableName(SourceItem::TABLE_NAME_SOURCE_ITEM);

        $committed = $connection->select()
            ->from(
                $reservationTable,
                [
                    ReservationInterface::SOURCE_CODE,
                    ReservationInterface::SKU,
                    'reserved' => 'SUM(' . ReservationInterface::QUANTITY . ')',
                ]
            )
            ->where(ReservationInterface::SOURCE_CODE . ' IS NOT NULL')
            ->group([ReservationInterface::SOURCE_CODE, ReservationInterface::SKU])
            ->having('SUM(' . ReservationInterface::QUANTITY . ') < ?', -self::EPSILON);

        $physicalExpr = new Expression('COALESCE(si.' . SourceItemInterface::QUANTITY . ', 0)');
        $select = $connection->select()
            ->from(
                ['r' => $committed],
                [
                    'source_code' => 'r.' . ReservationInterface::SOURCE_CODE,
                    'sku' => 'r.' . ReservationInterface::SKU,
                    'reserved' => 'r.reserved',
                    'physical' => $physicalExpr,
                ]
            )
            ->joinLeft(
                ['si' => $sourceItemTable],
                'si.' . SourceItemInterface::SOURCE_CODE . ' = r.' . ReservationInterface::SOURCE_CODE
                . ' AND si.' . SourceItemInterface::SKU . ' = r.' . ReservationInterface::SKU
                . ' AND si.' . SourceItemInterface::STATUS . ' = ' . (int)SourceItemInterface::STATUS_IN_STOCK,
                []
            )
            ->where($physicalExpr . ' + r.reserved < ?', -self::EPSILON)
            ->limit($limit);

        $result = [];
        foreach ($connection->fetchAll($select) as $row) {
            $reserved = (float)$row['reserved'];
            $physical = (float)$row['physical'];
            $result[] = [
                'source_code' => (string)$row['source_code'],
                'sku' => (string)$row['sku'],
                'physical' => $physical,
                'reserved' => $reserved,
                'delta' => $physical + $reserved,
            ];
        }

        return $result;
    }
}
