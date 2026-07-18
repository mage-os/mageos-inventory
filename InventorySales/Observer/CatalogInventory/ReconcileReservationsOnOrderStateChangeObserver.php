<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Observer\CatalogInventory;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\InventorySales\Model\SourceReservation\ReconcileOrderReservations;
use Magento\InventorySales\Model\SourceReservation\ReconciliationConfig;
use Magento\Sales\Model\Order;

/**
 * Reconcile an order's reservations when it transitions to a final state. This
 * catches releases that a failed or bypassed cancel/credit-memo observer never
 * appended, closing the residual-negative window that a physical deduction ↔
 * reservation binding cannot cover (cancel has no paired physical operation).
 * Bounded by the compensation clamp, so it cannot over-release. Opt-in.
 */
class ReconcileReservationsOnOrderStateChangeObserver implements ObserverInterface
{
    private const TERMINAL_STATES = [Order::STATE_COMPLETE, Order::STATE_CLOSED, Order::STATE_CANCELED];

    /**
     * @param ReconciliationConfig $reconciliationConfig
     * @param ReconcileOrderReservations $reconcileOrderReservations
     */
    public function __construct(
        private readonly ReconciliationConfig $reconciliationConfig,
        private readonly ReconcileOrderReservations $reconcileOrderReservations
    ) {
    }

    /**
     * @inheritdoc
     */
    public function execute(Observer $observer): void
    {
        if (!$this->reconciliationConfig->isCancelRefundReconciliationEnabled()) {
            return;
        }

        $order = $observer->getEvent()->getOrder();
        if (!$order instanceof Order) {
            return;
        }

        $state = (string)$order->getState();
        if ($state === (string)$order->getOrigData('state') || !in_array($state, self::TERMINAL_STATES, true)) {
            return;
        }

        $incrementId = (string)$order->getIncrementId();
        if ($incrementId === '') {
            return;
        }

        $this->reconcileOrderReservations->execute((int)$order->getId(), $incrementId, $state);
    }
}
