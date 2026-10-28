<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Model\SourceReservation;

use Magento\InventoryIndexer\Indexer\Stock\StockIndexer;
use Magento\InventorySales\Model\ResourceModel\SourceReservation\GetTerminalOrdersWithResidualReservations;

/**
 * Reconcile a batch of terminal orders that still carry a residual reservation
 * balance, then reindex the affected stocks so their salable qty reflects the
 * restored availability. Bounded per run; the caller is told when the limit was
 * reached so nothing is silently left behind.
 */
class ReconcileReservationsSweep
{
    /**
     * @param GetTerminalOrdersWithResidualReservations $getTerminalOrdersWithResidualReservations
     * @param ReconcileOrderReservations $reconcileOrderReservations
     * @param StockIndexer $stockIndexer
     */
    public function __construct(
        private readonly GetTerminalOrdersWithResidualReservations $getTerminalOrdersWithResidualReservations,
        private readonly ReconcileOrderReservations $reconcileOrderReservations,
        private readonly StockIndexer $stockIndexer
    ) {
    }

    /**
     * Run one sweep batch.
     *
     * @param int $limit
     * @param bool $dryRun
     * @return array{orders:int, compensations:int, stock_ids:int[], limit_reached:bool}
     */
    public function execute(int $limit, bool $dryRun = false): array
    {
        $orders = $this->getTerminalOrdersWithResidualReservations->execute($limit);

        $compensations = 0;
        $stockIds = [];
        foreach ($orders as $order) {
            $made = $this->reconcileOrderReservations->execute(
                $order['object_id'],
                $order['increment_id'],
                $order['state'],
                $dryRun
            );
            $compensations += count($made);
            foreach ($made as $entry) {
                $stockIds[$entry['stock_id']] = $entry['stock_id'];
            }
        }

        $stockIds = array_values($stockIds);
        if (!$dryRun && !empty($stockIds)) {
            $this->stockIndexer->executeList($stockIds);
        }

        return [
            'orders' => count($orders),
            'compensations' => $compensations,
            'stock_ids' => $stockIds,
            'limit_reached' => count($orders) >= $limit,
        ];
    }
}
