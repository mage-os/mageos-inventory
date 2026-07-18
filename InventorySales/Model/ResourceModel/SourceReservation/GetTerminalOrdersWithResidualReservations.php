<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Model\ResourceModel\SourceReservation;

use Magento\Framework\App\ResourceConnection;
use Magento\InventoryReservationsApi\Model\ReservationInterface;
use Magento\Sales\Model\Order;

/**
 * Find orders in a final state that still carry a net-negative reservation
 * balance: since a terminal order's expected reservation is zero, any remaining
 * negative balance is a release that was never appended (a gap-C residue),
 * including drift from direct DB edits or third-party state changes that the
 * synchronous safety net never saw.
 */
class GetTerminalOrdersWithResidualReservations
{
    private const EPSILON = 0.000001;
    private const TERMINAL_STATES = [Order::STATE_COMPLETE, Order::STATE_CLOSED, Order::STATE_CANCELED];

    /**
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /**
     * Get up to $limit terminal orders with a net-negative residual, as rows.
     *
     * @param int $limit
     * @return array<int, array{object_id:int, increment_id:string, state:string}>
     */
    public function execute(int $limit): array
    {
        $connection = $this->resourceConnection->getConnection();
        $incrementIdExpr = sprintf(
            "COALESCE(%s, JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.object_increment_id')))",
            ReservationInterface::OBJECT_INCREMENT_ID
        );
        $residualSelect = $connection->select()
            ->from(
                $this->resourceConnection->getTableName('inventory_reservation'),
                ['increment_id' => $incrementIdExpr]
            )
            ->group('increment_id')
            ->having('SUM(' . ReservationInterface::QUANTITY . ') < ?', -self::EPSILON);

        $select = $connection->select()
            ->from(
                ['so' => $this->resourceConnection->getTableName('sales_order')],
                ['object_id' => 'entity_id', 'increment_id' => 'increment_id', 'state' => 'state']
            )
            ->join(
                ['residual' => $residualSelect],
                'residual.increment_id = so.increment_id',
                []
            )
            ->where('so.state IN (?)', self::TERMINAL_STATES)
            ->limit($limit);

        $rows = [];
        foreach ($connection->fetchAll($select) as $row) {
            $rows[] = [
                'object_id' => (int)$row['object_id'],
                'increment_id' => (string)$row['increment_id'],
                'state' => (string)$row['state'],
            ];
        }

        return $rows;
    }
}
