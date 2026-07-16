<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Plugin\InventoryReservationsApi;

use Magento\InventoryReservationsApi\Model\AppendReservationsInterface;
use Magento\InventoryStockVisualizer\Model\Cache\PurgeOnReservations;
use Psr\Log\LoggerInterface;

/**
 * Purge the visualizer fragment for every SKU whose reservations just changed.
 *
 * Runs at the append chokepoint so it covers every demand path (order placement,
 * cancel, refund, RMA, import). Cache purging is best-effort: a failure here must
 * never break the reservation write, so throwables are swallowed and logged.
 */
class PurgeStockVisualizerCachePlugin
{
    /**
     * @param PurgeOnReservations $purgeOnReservations
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly PurgeOnReservations $purgeOnReservations,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param AppendReservationsInterface $subject
     * @param null $result
     * @param array $reservations
     * @return null
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterExecute(AppendReservationsInterface $subject, $result, array $reservations)
    {
        try {
            $this->purgeOnReservations->execute($reservations);
        } catch (\Throwable $e) {
            $this->logger->error('Stock visualizer cache purge failed: ' . $e->getMessage(), ['exception' => $e]);
        }

        return $result;
    }
}
