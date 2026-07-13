<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventorySales\Test\Unit\Cron;

use Magento\InventorySales\Cron\ReconcileReservations;
use Magento\InventorySales\Model\SourceReservation\ReconcileReservationsSweep;
use Magento\InventorySales\Model\SourceReservation\ReconciliationConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ReconcileReservationsTest extends TestCase
{
    /**
     * @var ReconciliationConfig|MockObject
     */
    private $config;

    /**
     * @var ReconcileReservationsSweep|MockObject
     */
    private $sweep;

    /**
     * @var ReconcileReservations
     */
    private $cron;

    protected function setUp(): void
    {
        $this->config = $this->createMock(ReconciliationConfig::class);
        $this->sweep = $this->createMock(ReconcileReservationsSweep::class);
        $this->cron = new ReconcileReservations(
            $this->config,
            $this->sweep,
            $this->createMock(LoggerInterface::class)
        );
    }

    public function testSkipsWhenSweepDisabled(): void
    {
        $this->config->method('isSweepEnabled')->willReturn(false);
        $this->sweep->expects(self::never())->method('execute');

        $this->cron->execute();
    }

    public function testRunsSweepWhenEnabled(): void
    {
        $this->config->method('isSweepEnabled')->willReturn(true);
        $this->sweep->expects(self::once())->method('execute')
            ->willReturn(['orders' => 0, 'compensations' => 0, 'stock_ids' => [], 'limit_reached' => false]);

        $this->cron->execute();
    }
}
