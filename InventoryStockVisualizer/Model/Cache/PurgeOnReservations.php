<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Model\Cache;

use Magento\InventoryReservationsApi\Model\ReservationInterface;

/**
 * Purge the visualizer cache for products whose displayed value changed after reservations were appended.
 *
 * This is the demand seam: it groups the appended reservation deltas, asks the shared decider which
 * SKUs actually changed, and hands them to the dispatcher, which flushes inline or offloads to the
 * coalescing queue depending on the configured strategy.
 */
class PurgeOnReservations
{
    /**
     * @param ResolveSkusToPurge $resolveSkusToPurge
     * @param DispatchPurge $dispatchPurge
     */
    public function __construct(
        private readonly ResolveSkusToPurge $resolveSkusToPurge,
        private readonly DispatchPurge $dispatchPurge
    ) {
    }

    /**
     * @param ReservationInterface[] $reservations
     * @return void
     */
    public function execute(array $reservations): void
    {
        $deltas = $this->groupDeltas($reservations);
        if (!$deltas) {
            return;
        }

        $this->dispatchPurge->execute($this->resolveSkusToPurge->execute($deltas));
    }

    /**
     * Group reservation deltas by stock and SKU, keeping the total and per-source deltas.
     *
     * @param ReservationInterface[] $reservations
     * @return array<int, array<string, array{total: float, bySource: array<string, float>}>>
     */
    private function groupDeltas(array $reservations): array
    {
        $deltas = [];
        foreach ($reservations as $reservation) {
            $quantity = (float) $reservation->getQuantity();
            if ($quantity === 0.0) {
                continue;
            }
            $stockId = (int) $reservation->getStockId();
            $sku = (string) $reservation->getSku();
            $sourceCode = (string) ($reservation->getSourceCode() ?? '');

            if (!isset($deltas[$stockId][$sku])) {
                $deltas[$stockId][$sku] = ['total' => 0.0, 'bySource' => []];
            }
            $deltas[$stockId][$sku]['total'] += $quantity;
            if ($sourceCode !== '') {
                $deltas[$stockId][$sku]['bySource'][$sourceCode] =
                    ($deltas[$stockId][$sku]['bySource'][$sourceCode] ?? 0.0) + $quantity;
            }
        }

        return $deltas;
    }
}
