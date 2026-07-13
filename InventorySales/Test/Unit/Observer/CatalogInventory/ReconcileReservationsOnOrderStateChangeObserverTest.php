<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventorySales\Test\Unit\Observer\CatalogInventory;

use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\InventorySales\Model\SourceReservation\ReconcileOrderReservations;
use Magento\InventorySales\Model\SourceReservation\ReconciliationConfig;
use Magento\InventorySales\Observer\CatalogInventory\ReconcileReservationsOnOrderStateChangeObserver;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ReconcileReservationsOnOrderStateChangeObserverTest extends TestCase
{
    /**
     * @var ReconciliationConfig|MockObject
     */
    private $config;

    /**
     * @var ReconcileOrderReservations|MockObject
     */
    private $engine;

    /**
     * @var ReconcileReservationsOnOrderStateChangeObserver
     */
    private $observer;

    protected function setUp(): void
    {
        $this->config = $this->createMock(ReconciliationConfig::class);
        $this->engine = $this->createMock(ReconcileOrderReservations::class);
        $this->observer = new ReconcileReservationsOnOrderStateChangeObserver($this->config, $this->engine);
    }

    public function testSkipsWhenDisabled(): void
    {
        $this->config->method('isCancelRefundReconciliationEnabled')->willReturn(false);
        $this->engine->expects(self::never())->method('execute');

        $this->observer->execute($this->observerFor($this->order('complete', 'processing')));
    }

    public function testSkipsWhenNoTransition(): void
    {
        $this->config->method('isCancelRefundReconciliationEnabled')->willReturn(true);
        $this->engine->expects(self::never())->method('execute');

        $this->observer->execute($this->observerFor($this->order('complete', 'complete')));
    }

    public function testSkipsWhenTransitionIsNotTerminal(): void
    {
        $this->config->method('isCancelRefundReconciliationEnabled')->willReturn(true);
        $this->engine->expects(self::never())->method('execute');

        $this->observer->execute($this->observerFor($this->order('processing', 'pending')));
    }

    public function testReconcilesOnTerminalTransition(): void
    {
        $this->config->method('isCancelRefundReconciliationEnabled')->willReturn(true);
        $this->engine->expects(self::once())->method('execute')->with(7, '000000007', Order::STATE_CANCELED);

        $this->observer->execute($this->observerFor($this->order(Order::STATE_CANCELED, 'processing')));
    }

    private function order(string $state, ?string $origState): Order
    {
        $order = $this->createMock(Order::class);
        $order->method('getState')->willReturn($state);
        $order->method('getOrigData')->with('state')->willReturn($origState);
        $order->method('getIncrementId')->willReturn('000000007');
        $order->method('getId')->willReturn(7);

        return $order;
    }

    private function observerFor(Order $order): Observer
    {
        $event = new Event(['order' => $order]);
        $observer = new Observer();
        $observer->setEvent($event);

        return $observer;
    }
}
